<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import MediaLibrary from './MediaLibrary.vue'
import MediaPicker from './MediaPicker.vue'
import RichTextEditor from './RichTextEditor.vue'

const user = ref(null)
const error = ref('')
const notice = ref('')
const form = ref({ email: '', password: '' })
const navOpen = ref(false)
const busy = ref(false)
const view = ref('orders')
const profileOpen = ref(false)
const pwModal = ref(false)
const pwForm = ref({ current_password: '', new_password: '', confirm_password: '' })
const pwBusy = ref(false)
const pwError = ref('')
const rupiah = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 })
const STOREFRONT = 'https://vpictura.com'
function mediaUrl(url) { if (!url) return ''; return /^https?:/i.test(url) ? url : `${STOREFRONT}${url}` }
const navItems = [
  { key: 'dashboard', label: 'Dashboard' },
  { key: 'orders', label: 'Pesanan' },
  { key: 'categories', label: 'Kategori' },
  { key: 'products', label: 'Produk' },
  { key: 'media', label: 'Media' },
  { key: 'customers', label: 'Pelanggan' },
  { key: 'eventusers', label: 'Event User' },
  { key: 'uploads', label: 'Upload Desain' },
  { key: 'settings', label: 'Pengaturan' },
]
const titles = { dashboard: 'Dashboard', orders: 'Pesanan', categories: 'Kategori', products: 'Produk', media: 'Media', customers: 'Pelanggan', eventusers: 'Event User', uploads: 'Upload Desain', settings: 'Pengaturan' }
const currentTitle = computed(() => titles[view.value] || 'Dashboard')
const firstName = computed(() => (user.value?.name || '').trim().split(/\s+/)[0] || '')
watch([user, currentTitle], ([u, t]) => { document.title = u ? `${t} · Vita Pictura Admin` : 'Masuk · Vita Pictura Admin' }, { immediate: true })

async function api(path, body) {
  const r = await fetch(`/api/Admin/${path}`, { method: body ? 'POST' : 'GET', credentials: 'include', headers: body ? { 'Content-Type': 'application/json' } : {}, body: body ? JSON.stringify(body) : undefined })
  // Sesi berakhir (401) padahal sudah login → muat ulang halaman (kembali ke layar masuk)
  // alih-alih menampilkan toast "Unauthorized".
  if (r.status === 401 && user.value) { window.location.reload(); throw new Error('Unauthorized') }
  const p = await r.json()
  if (!r.ok || !p.status) throw new Error(p.message)
  return p.data
}
let flashTimer
function flash(message) { notice.value = message; window.clearTimeout(flashTimer); flashTimer = window.setTimeout(() => { notice.value = '' }, 3500) }

// ---- Auth ----
async function login() {
  busy.value = true; error.value = ''
  try { user.value = await api('Auth/login', form.value); form.value = { email: '', password: '' }; view.value = 'dashboard'; await loadDashboard(); flash('Berhasil masuk.') }
  catch (e) { error.value = e.message }
  finally { busy.value = false }
}
async function logout() { profileOpen.value = false; try { await api('Auth/logout', {}) } catch {} user.value = null; navOpen.value = false }
function openChangePassword() { profileOpen.value = false; pwForm.value = { current_password: '', new_password: '', confirm_password: '' }; pwError.value = ''; pwModal.value = true }
function closeChangePassword() { pwModal.value = false }
async function submitChangePassword() {
  pwError.value = ''
  if (pwForm.value.new_password.length < 8) { pwError.value = 'Password baru minimal 8 karakter.'; return }
  if (pwForm.value.new_password !== pwForm.value.confirm_password) { pwError.value = 'Konfirmasi password tidak cocok.'; return }
  pwBusy.value = true
  try { await api('Auth/changePassword', pwForm.value); pwModal.value = false; flash('Password berhasil diubah.') }
  catch (e) { pwError.value = e.message }
  finally { pwBusy.value = false }
}
function switchView(key) { view.value = key; navOpen.value = false; loadView(key) }
function loadView(key) { if (key === 'dashboard') return loadDashboard(); if (key === 'orders') return loadOrders(); if (key === 'categories') return loadCategories(); if (key === 'products') return loadProducts(); if (key === 'customers') return loadCustomers(); if (key === 'eventusers') return loadEventUsers(); if (key === 'uploads') return loadUploads(); if (key === 'settings') return loadSettings() }

// ---- Orders ----
const orders = ref([])
const search = ref('')
const statusFilter = ref('')
const savingId = ref(null)
const loading = ref(false)
const expiryHours = ref(25)
const tracking = ref({})
function parseDate(value) { if (!value) return null; const d = new Date(String(value).replace(' ', 'T')); return Number.isNaN(d.getTime()) ? null : d }
function ageText(value) { const d = parseDate(value); if (!d) return ''; const hours = Math.max(0, Math.floor((Date.now() - d.getTime()) / 3600000)); const days = Math.floor(hours / 24); return days >= 1 ? `${days} hari ${hours % 24} jam` : `${hours} jam` }
function dueText(value, expiry) { if (expiry) { const e = parseDate(expiry); if (e) return e.toLocaleString('id-ID', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) } const d = parseDate(value); if (!d) return '—'; return new Date(d.getTime() + (Number(expiryHours.value) || 25) * 3600000).toLocaleString('id-ID', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) }
async function loadOrders() { loading.value = true; try { const d = await api('Orders/index'); orders.value = d.items || []; expiryHours.value = d.expiryHours || 25 } catch (e) { error.value = e.message } finally { loading.value = false } }
async function update(order, status) {
  const previous = order.status
  order.status = status
  savingId.value = order.id
  try { await api(`Orders/update-status/${order.id}`, { status }); flash('Status pesanan diperbarui.') }
  catch (e) { order.status = previous; error.value = e.message }
  finally { savingId.value = null }
}
const orderModal = ref(false)
const orderDetail = ref(null)
const orderLoading = ref(false)
const savingOrder = ref(false)
const deliveryForm = ref({ tracking_number: '', courier_company: '', courier_service: '', status: 'shipped' })
async function openOrder(row) {
  orderModal.value = true; orderDetail.value = null; orderLoading.value = true
  try {
    const d = await api(`Orders/show/${row.id}`)
    orderDetail.value = d
    deliveryForm.value = { tracking_number: d.delivery?.tracking_number || '', courier_company: d.delivery?.courier_company || d.courier_company || '', courier_service: d.delivery?.courier_service || d.courier_service || '', status: d.delivery?.status || 'shipped' }
  } catch (e) { error.value = e.message } finally { orderLoading.value = false }
}
function closeOrder() { orderModal.value = false; orderDetail.value = null }
async function saveDelivery() {
  savingOrder.value = true
  try { await api(`Orders/save-delivery/${orderDetail.value.id}`, deliveryForm.value); await openOrder({ id: orderDetail.value.id }); await loadOrders(); flash('Pengiriman disimpan.') }
  catch (e) { error.value = e.message } finally { savingOrder.value = false }
}
async function markPaid() {
  if (!window.confirm('Tandai pesanan ini lunas?')) return
  savingOrder.value = true
  try { await api(`Orders/mark-paid/${orderDetail.value.id}`, {}); await openOrder({ id: orderDetail.value.id }); await loadOrders(); flash('Pesanan ditandai lunas.') }
  catch (e) { error.value = e.message } finally { savingOrder.value = false }
}
const orderTabs = [ { key: '', label: 'Semua' }, { key: 'pending_payment', label: 'Menunggu' }, { key: 'processing', label: 'Diproses' }, { key: 'shipped', label: 'Dikirim' }, { key: 'completed', label: 'Selesai' }, { key: 'cancelled', label: 'Batal' }, { key: 'expired', label: 'Kedaluwarsa' } ]
function orderCount(status) { return status ? orders.value.filter((o) => o.status === status).length : orders.value.length }
async function bookOrder() { if (!orderDetail.value) return; if (!window.confirm('Buat order pengiriman ke Biteship?')) return; savingOrder.value = true; try { await api(`Orders/book/${orderDetail.value.id}`, {}); await openOrder({ id: orderDetail.value.id }); await loadOrders(); flash('Pesanan dikirim (Biteship).') } catch (e) { error.value = e.message } finally { savingOrder.value = false } }
async function completeOrder() { if (!orderDetail.value) return; if (!window.confirm('Tandai pesanan selesai?')) return; savingOrder.value = true; try { await api(`Orders/mark-completed/${orderDetail.value.id}`, {}); await openOrder({ id: orderDetail.value.id }); await loadOrders(); flash('Pesanan selesai.') } catch (e) { error.value = e.message } finally { savingOrder.value = false } }
async function cancelOrder() { if (!orderDetail.value) return; const note = window.prompt('Alasan pembatalan:'); if (note === null) return; savingOrder.value = true; try { await api(`Orders/cancel/${orderDetail.value.id}`, { note }); await openOrder({ id: orderDetail.value.id }); await loadOrders(); flash('Pesanan dibatalkan.') } catch (e) { error.value = e.message } finally { savingOrder.value = false } }
function formatDateTime(value) { if (!value) return '—'; const d = new Date(String(value).replace(' ', 'T')); return Number.isNaN(d.getTime()) ? value : d.toLocaleString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) }
const filteredOrders = computed(() => {
  const query = search.value.trim().toLowerCase()
  return orders.value.filter((order) => {
    const matchQuery = !query || order.order_number.toLowerCase().includes(query) || (order.customer_name || '').toLowerCase().includes(query)
    const matchStatus = !statusFilter.value || order.status === statusFilter.value
    return matchQuery && matchStatus
  })
})
function countBy(status) { return orders.value.filter((order) => order.status === status).length }
const cardBusy = ref(null)
async function markPaidOrder(order) { if (!window.confirm('Tandai pesanan ini lunas?')) return; cardBusy.value = order.id; try { await api(`Orders/mark-paid/${order.id}`, {}); await loadOrders(); flash('Pesanan ditandai lunas.') } catch (e) { error.value = e.message } finally { cardBusy.value = null } }
async function completeOrderById(order) { if (!window.confirm('Tandai pesanan selesai?')) return; cardBusy.value = order.id; try { await api(`Orders/mark-completed/${order.id}`, {}); await loadOrders(); flash('Pesanan selesai.') } catch (e) { error.value = e.message } finally { cardBusy.value = null } }
async function cancelOrderById(order) { const note = window.prompt('Alasan pembatalan:'); if (note === null) return; cardBusy.value = order.id; try { await api(`Orders/cancel/${order.id}`, { note }); await loadOrders(); flash('Pesanan dibatalkan.') } catch (e) { error.value = e.message } finally { cardBusy.value = null } }
async function bookOrderWith(order, method) { const label = method ? `metode ${method}` : 'Biteship'; if (!window.confirm(`Buat order pengiriman (${label})?`)) return; cardBusy.value = order.id; savingOrder.value = true; try { await api(`Orders/book/${order.id}`, { collection_method: method || '' }); if (orderModal.value && orderDetail.value?.id === order.id) await openOrder({ id: order.id }); await loadOrders(); flash('Pesanan dikirim (Biteship).') } catch (e) { error.value = e.message } finally { cardBusy.value = null; savingOrder.value = false } }
async function loadTracking(order) { tracking.value = { ...tracking.value, [order.id]: { loading: true, history: [], status: '', error: '' } }; try { const d = await api(`Orders/tracking/${order.id}`, {}); tracking.value = { ...tracking.value, [order.id]: { loading: false, history: d.history || [], status: d.status || '', error: '' } }; await loadOrders() } catch (e) { tracking.value = { ...tracking.value, [order.id]: { loading: false, history: [], status: '', error: e.message } } } }
async function refreshTracking(order) { if (!order) return; savingOrder.value = true; try { const d = await api(`Orders/tracking/${order.id}`, {}); if (orderDetail.value && orderDetail.value.id === order.id) { orderDetail.value = { ...orderDetail.value, delivery: { ...(orderDetail.value.delivery || {}), tracking_history: d.history || [], status: d.status || orderDetail.value.delivery?.status } }; } await loadOrders(); flash('Tracking diperbarui.') } catch (e) { error.value = e.message } finally { savingOrder.value = false } }
const shippingMethodsBusy = ref(false)
async function fetchMethods(order) { if (!order) return; shippingMethodsBusy.value = true; try { const d = await api(`Orders/methods/${order.id}`, {}); if (orderDetail.value && orderDetail.value.id === order.id) { orderDetail.value = { ...orderDetail.value, available_collection_method: d.methods || [] }; } await loadOrders(); flash((d.methods && d.methods.length) ? 'Pilih metode pengiriman.' : 'Tidak ada metode tersedia — pakai Input manual.') } catch (e) { error.value = e.message } finally { shippingMethodsBusy.value = false } }
// ---- Ship modal (proses kirim terpisah) ----
const shipModal = ref(false)
const shipOrder = ref(null)
const shipConfirm = ref('')
const shipBusy = ref(false)
const shipMethods = ref([])
function openShip(order) { shipOrder.value = order; shipConfirm.value = ''; shipMethods.value = Array.isArray(order.available_collection_method) ? [...order.available_collection_method] : []; shipModal.value = true; if (!shipMethods.value.length) loadShipMethods() }
function closeShip() { shipModal.value = false; shipOrder.value = null; shipConfirm.value = '' }
async function loadShipMethods() { if (!shipOrder.value) return; shipBusy.value = true; try { const d = await api(`Orders/methods/${shipOrder.value.id}`, {}); shipMethods.value = d.methods || [] } catch (e) { error.value = e.message } finally { shipBusy.value = false } }
async function doShip(method) { if (!shipOrder.value) return; if (shipConfirm.value.trim() !== 'KIRIM') { error.value = 'Ketik KIRIM untuk konfirmasi.'; return } shipBusy.value = true; try { const num = shipOrder.value.order_number; await api(`Orders/book/${shipOrder.value.id}`, { collection_method: method || '' }); closeShip(); await loadOrders(); flash('Pesanan ' + num + ' diproses kirim (status: Dikirim).') } catch (e) { error.value = e.message } finally { shipBusy.value = false } }
function formatDate(value) { if (!value) return ''; const date = new Date(String(value).replace(' ', 'T')); return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) }

// ---- Categories ----
const categories = ref([])
const catLoading = ref(false)
const catModal = ref(false)
const catSaving = ref(false)
const catForm = ref(emptyCategory())
function emptyCategory() { return { id: null, name: '', slug: '', description: '', image_url: '', sort_order: 0, is_featured: false, status: 'published' } }
async function loadCategories() { catLoading.value = true; try { categories.value = (await api('Categories/index')).items || [] } catch (e) { error.value = e.message } finally { catLoading.value = false } }
function openCategory(row) {
  catForm.value = row
    ? { id: row.id, name: row.name, slug: row.slug, description: row.description || '', image_url: row.image_url || '', sort_order: row.sort_order || 0, is_featured: !!row.is_featured, status: row.status }
    : emptyCategory()
  catModal.value = true
}
function closeCategory() { catModal.value = false }
function slugify(value) { return String(value || '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '') }
function onCategoryName() { if (!catForm.value.id) catForm.value.slug = slugify(catForm.value.name) }
async function saveCategory() {
  catSaving.value = true
  try {
    const body = { ...catForm.value, is_featured: catForm.value.is_featured ? 1 : 0 }
    if (catForm.value.id) await api(`Categories/save/${catForm.value.id}`, body)
    else await api('Categories/save', body)
    catModal.value = false
    await loadCategories()
    flash('Kategori disimpan.')
  } catch (e) { error.value = e.message } finally { catSaving.value = false }
}
async function removeCategory(row) {
  if (!window.confirm(`Hapus kategori "${row.name}"?`)) return
  try { await api(`Categories/remove/${row.id}`, {}); await loadCategories(); flash('Kategori dihapus.') }
  catch (e) { error.value = e.message }
}

// ---- Products ----
const products = ref([])
const prodLoading = ref(false)
const prodModal = ref(false)
const prodSaving = ref(false)
const prodSearch = ref('')
const prodCategory = ref('')
const prodStatus = ref('')
const prodForm = ref(emptyProduct())
function emptyProduct() { return { id: null, name: '', slug: '', category_id: '', short_description: '', description: '', base_price: 0, weight_grams: 0, length_mm: '', width_mm: '', height_mm: '', cover_image_url: '', is_featured: false, status: 'published', perlu_file: false, mal: [] } }
async function loadProducts() { prodLoading.value = true; try { if (!categories.value.length) { try { categories.value = (await api('Categories/index')).items || [] } catch (e) {} } products.value = (await api('Products/index')).items || [] } catch (e) { error.value = e.message } finally { prodLoading.value = false } }
const filteredProducts = computed(() => {
  const q = prodSearch.value.trim().toLowerCase()
  return products.value.filter((p) => {
    const mq = !q || p.name.toLowerCase().includes(q)
    const mc = !prodCategory.value || String(p.category_id) === String(prodCategory.value)
    const ms = !prodStatus.value || p.status === prodStatus.value
    return mq && mc && ms
  })
})
function mapProduct(p) { return { id: p.id, name: p.name, slug: p.slug, category_id: p.category_id ?? '', short_description: p.short_description || '', description: p.description || '', base_price: Number(p.base_price) || 0, weight_grams: Number(p.weight_grams) || 0, length_mm: p.length_mm ?? '', width_mm: p.width_mm ?? '', height_mm: p.height_mm ?? '', cover_image_url: p.cover_image_url || '', is_featured: !!p.is_featured, status: p.status, perlu_file: !!p.perlu_file, mal: Array.isArray(p.mal) ? p.mal : [] } }
async function openProduct(row) {
  if (row && row.id) {
    try { prodForm.value = mapProduct(await api(`Products/show/${row.id}`)) } catch (e) { error.value = e.message; return }
    await loadMedia(prodForm.value.id)
  } else { prodForm.value = emptyProduct(); prodMedia.value = [] }
  prodModal.value = true
}
function closeProduct() { prodModal.value = false; prodMedia.value = []; variantModal.value = false; variants.value = []; tabsModal.value = false; tabsList.value = []; tabModal.value = false }
function onProductName() { if (!prodForm.value.id) prodForm.value.slug = slugify(prodForm.value.name) }
async function saveProduct() {
  prodSaving.value = true
  try {
    const body = { ...prodForm.value, is_featured: prodForm.value.is_featured ? 1 : 0, category_id: prodForm.value.category_id === '' ? null : prodForm.value.category_id }
    if (prodForm.value.id) await api(`Products/save/${prodForm.value.id}`, body)
    else await api('Products/save', body)
    prodModal.value = false
    await loadProducts()
    flash('Produk disimpan.')
  } catch (e) { error.value = e.message } finally { prodSaving.value = false }
}
async function removeProduct(row) {
  if (!window.confirm(`Hapus produk "${row.name}"?`)) return
  try { await api(`Products/remove/${row.id}`, {}); await loadProducts(); flash('Produk dihapus.') }
  catch (e) { error.value = e.message }
}

// ---- Product media ----
const prodMedia = ref([])
async function loadMedia(pid) { try { prodMedia.value = (await api(`Products/media/${pid}`)).items || [] } catch (e) { prodMedia.value = [] } }
async function removeMedia(item) { if (!window.confirm('Hapus media ini?')) return; try { await api(`Products/remove-media/${item.id}`, {}); await loadMedia(prodForm.value.id); flash('Media dihapus.') } catch (e) { error.value = e.message } }
async function moveMedia(item, dir) { const list = [...prodMedia.value]; const i = list.findIndex((m) => m.id === item.id); const j = i + dir; if (j < 0 || j >= list.length) return; [list[i], list[j]] = [list[j], list[i]]; prodMedia.value = list; try { await api(`Products/reorder-media/${prodForm.value.id}`, { order: list.map((m) => m.id) }) } catch (e) { error.value = e.message } }
async function setCover(item) { try { const d = await api(`Products/set-cover/${prodForm.value.id}`, { media_id: item.id }); prodForm.value.cover_image_url = d.coverImage; flash('Cover diperbarui.') } catch (e) { error.value = e.message } }
async function setMediaSuffix(item) {
  const current = item.image_suffix || gallerySuffix(item.image_key)
  const suffix = window.prompt('Suffix gambar varian (kosongkan = gambar utama "m").\nContoh: merah, full_logo', current || '')
  if (suffix === null) return
  try { await api(`Products/update-media/${item.id}`, { image_suffix: suffix.trim() }); await loadMedia(prodForm.value.id); flash('Suffix gambar disimpan.') } catch (e) { error.value = e.message }
}

// ---- Media picker (dari library) ----
const pickerOpen = ref(false)
const pickerMode = ref('main')
function openMediaPicker(mode) { pickerMode.value = mode; pickerOpen.value = true }
async function onMediaPick(file) {
  pickerOpen.value = false
  if (pickerMode.value === 'gallery') {
    if (!prodForm.value.id) return
    try { await api(`Products/add-media/${prodForm.value.id}`, { media_id: file.id }); await loadMedia(prodForm.value.id); flash('Gambar ditambahkan ke galeri.') } catch (e) { error.value = e.message }
  } else if (pickerMode.value === 'mal') {
    if (!prodForm.value.mal.some((m) => m.url === file.url)) prodForm.value.mal.push({ name: file.name, url: file.url })
    flash('File mal ditambahkan.')
  } else {
    prodForm.value.cover_image_url = file.url
    flash('Gambar utama dipilih.')
  }
}
async function addLibraryMedia() {
  if (!prodForm.value.id) { error.value = 'Simpan produk dulu sebelum menambah galeri.'; return }
  openMediaPicker('gallery')
}
async function onMediaPickMany(files) {
  pickerOpen.value = false
  if (!Array.isArray(files) || !files.length) return
  if (pickerMode.value === 'gallery') {
    if (!prodForm.value.id) return
    let added = 0
    for (const file of files) {
      try { await api(`Products/add-media/${prodForm.value.id}`, { media_id: file.id }); added++ } catch (e) { /* lewati yang gagal */ }
    }
    await loadMedia(prodForm.value.id)
    flash(`${added} gambar ditambahkan ke galeri.`)
  } else if (pickerMode.value === 'mal') {
    let added = 0
    for (const file of files) {
      if (!prodForm.value.mal.some((m) => m.url === file.url)) {
        prodForm.value.mal.push({ name: file.name, url: file.url })
        added++
      }
    }
    flash(`${added} file mal ditambahkan.`)
  }
}
function gallerySuffix(key) { const k = String(key || '').trim(); if (!k || !/^m(_|$)/.test(k) || k === 'm') return ''; return k.replace(/^m_?/, '') }
const gallerySuffixOptions = computed(() => {
  const seen = new Set()
  const out = []
  for (const m of prodMedia.value) {
    const suffix = String(m.image_suffix || gallerySuffix(m.image_key) || '').trim()
    if (!suffix || seen.has(suffix)) continue
    seen.add(suffix)
    out.push({ suffix, key: m.image_key, url: m.url })
  }
  return out
})

// ---- Variants (option groups & values) ----
const variantModal = ref(false)
const variants = ref([])
const variantLoading = ref(false)
const variantProduct = ref({ id: null, name: '' })
const groupModal = ref(false)
const groupSaving = ref(false)
const groupForm = ref(emptyGroup())
const valueModal = ref(false)
const valueSaving = ref(false)
const valueForm = ref(emptyValue())
function emptyGroup() { return { id: null, product_id: null, name: '', group_level: 1, parent_group_id: '', is_required: true, sort_order: 0 } }
function emptyValue() { return { id: null, option_group_id: null, name: '', image_suffix: '', parent_value_id: '', price_delta: 0, weight_delta_grams: 0, length_delta_mm: 0, width_delta_mm: 0, height_delta_mm: 0, sort_order: 0, is_active: true } }
const level1Groups = computed(() => variants.value.filter((g) => g.group_level === 1))
function subGroups(parent) { return variants.value.filter((g) => g.group_level === 2 && g.parent_group_id === parent.id) }
function valueName(id) { if (!id) return ''; for (const g of variants.value) { const v = g.values.find((x) => x.id === id); if (v) return v.name } return '' }
async function openVariants() { if (!prodForm.value.id) return; variantProduct.value = { id: prodForm.value.id, name: prodForm.value.name }; variantModal.value = true; await loadVariants() }
function closeVariants() { variantModal.value = false; variants.value = [] }
async function loadVariants() { variantLoading.value = true; try { variants.value = (await api(`Options/index/${variantProduct.value.id}`)).items || [] } catch (e) { error.value = e.message } finally { variantLoading.value = false } }
function openGroup(group, parentId) {
  groupForm.value = group
    ? { id: group.id, product_id: variantProduct.value.id, name: group.name, group_level: group.group_level, parent_group_id: group.parent_group_id ?? '', is_required: !!group.is_required, sort_order: group.sort_order }
    : { ...emptyGroup(), product_id: variantProduct.value.id, group_level: parentId ? 2 : 1, parent_group_id: parentId ?? '' }
  groupModal.value = true
}
async function saveGroup() {
  groupSaving.value = true
  try {
    const body = { ...groupForm.value, is_required: groupForm.value.is_required ? 1 : 0, product_id: variantProduct.value.id }
    await api(groupForm.value.id ? `Options/save-group/${groupForm.value.id}` : 'Options/save-group', body)
    groupModal.value = false; await loadVariants(); flash('Grup disimpan.')
  } catch (e) { error.value = e.message } finally { groupSaving.value = false }
}
async function removeGroup(group) { if (!window.confirm(`Hapus grup "${group.name}" beserta nilainya?`)) return; try { await api(`Options/remove-group/${group.id}`, {}); await loadVariants(); flash('Grup dihapus.') } catch (e) { error.value = e.message } }
function openValue(group, value) {
  valueForm.value = value
    ? { id: value.id, option_group_id: group.id, name: value.name, image_suffix: value.image_suffix || '', parent_value_id: value.parent_value_id ?? '', price_delta: value.price_delta, weight_delta_grams: value.weight_delta_grams, length_delta_mm: value.length_delta_mm, width_delta_mm: value.width_delta_mm, height_delta_mm: value.height_delta_mm, sort_order: value.sort_order, is_active: !!value.is_active }
    : { ...emptyValue(), option_group_id: group.id }
  valueModal.value = true
}
async function saveValue() {
  valueSaving.value = true
  try {
    const body = { ...valueForm.value, is_active: valueForm.value.is_active ? 1 : 0 }
    await api(valueForm.value.id ? `Options/save-value/${valueForm.value.id}` : 'Options/save-value', body)
    valueModal.value = false; await loadVariants(); flash('Nilai disimpan.')
  } catch (e) { error.value = e.message } finally { valueSaving.value = false }
}
async function removeValue(value) { if (!window.confirm(`Hapus nilai "${value.name}"?`)) return; try { await api(`Options/remove-value/${value.id}`, {}); await loadVariants(); flash('Nilai dihapus.') } catch (e) { error.value = e.message } }

// ---- Product detail tabs ----
const tabsModal = ref(false)
const tabsList = ref([])
const tabsLoading = ref(false)
const tabsProduct = ref({ id: null, name: '' })
const tabModal = ref(false)
const tabSaving = ref(false)
const tabForm = ref({ id: null, product_id: null, title: '', sort_order: 0, content_html: '' })
function openTabs() { if (!prodForm.value.id) return; tabsProduct.value = { id: prodForm.value.id, name: prodForm.value.name }; tabsModal.value = true; loadTabs() }
function closeTabs() { tabsModal.value = false; tabsList.value = []; tabModal.value = false }
async function loadTabs() { tabsLoading.value = true; try { tabsList.value = (await api(`Products/tabs/${tabsProduct.value.id}`)).items || [] } catch (e) { error.value = e.message } finally { tabsLoading.value = false } }
function openTab(tab) { tabForm.value = tab ? { id: tab.id, product_id: tabsProduct.value.id, title: tab.title, sort_order: tab.sort_order, content_html: tab.content_html || '' } : { id: null, product_id: tabsProduct.value.id, title: '', sort_order: tabsList.value.length + 1, content_html: '' }; tabModal.value = true }
async function saveTab() { tabSaving.value = true; try { await api(tabForm.value.id ? `Products/save-tab/${tabForm.value.id}` : 'Products/save-tab', { ...tabForm.value }); tabModal.value = false; await loadTabs(); flash('Tab disimpan.') } catch (e) { error.value = e.message } finally { tabSaving.value = false } }
async function removeTab(tab) { if (!window.confirm(`Hapus tab "${tab.title}"?`)) return; try { await api(`Products/remove-tab/${tab.id}`, {}); await loadTabs(); flash('Tab dihapus.') } catch (e) { error.value = e.message } }

// ---- Customers ----
const customers = ref([])
const custLoading = ref(false)
const custSearch = ref('')
const custModal = ref(false)
const custDetail = ref(null)
const custLoadingDetail = ref(false)
const custSaving = ref(false)
async function loadCustomers() { custLoading.value = true; try { customers.value = (await api('Customers/index')).items || [] } catch (e) { error.value = e.message } finally { custLoading.value = false } }
const filteredCustomers = computed(() => { const q = custSearch.value.trim().toLowerCase(); if (!q) return customers.value; return customers.value.filter((c) => (c.full_name || '').toLowerCase().includes(q) || (c.email || '').toLowerCase().includes(q) || String(c.phone || '').includes(q)) })
async function openCustomer(row) { custModal.value = true; custDetail.value = null; custLoadingDetail.value = true; try { custDetail.value = await api(`Customers/show/${row.id}`) } catch (e) { error.value = e.message } finally { custLoadingDetail.value = false } }
function closeCustomer() { custModal.value = false; custDetail.value = null }
async function toggleCustomerStatus() {
  if (!custDetail.value) return
  const next = custDetail.value.status === 'blocked' ? 'active' : 'blocked'
  if (!window.confirm(next === 'blocked' ? 'Blokir pelanggan ini?' : 'Aktifkan kembali pelanggan ini?')) return
  custSaving.value = true
  try { await api(`Customers/update-status/${custDetail.value.id}`, { status: next }); await openCustomer({ id: custDetail.value.id }); await loadCustomers(); flash('Status pelanggan diperbarui.') }
  catch (e) { error.value = e.message } finally { custSaving.value = false }
}

// ---- Event Users ----
const eventUsers = ref([])
const eventUsersLoading = ref(false)
const eventUsersDefaults = ref({ maxEvents: 3, maxPhotos: 200 })
const eventUsersWhitelistMode = ref('open')
const eventUserModal = ref(false)
const eventUserDetail = ref(null)
const eventUserLoading = ref(false)
const eventUserSaving = ref(false)
const eventUserLimits = ref({ max_events: '', max_photos_per_event: '' })
async function loadEventUsers() { eventUsersLoading.value = true; try { const d = await api('EventUsers/index'); eventUsers.value = d.items || []; if (d.defaults) eventUsersDefaults.value = d.defaults; eventUsersWhitelistMode.value = d.whitelistMode || 'open' } catch (e) { error.value = e.message } finally { eventUsersLoading.value = false } }
async function setWhitelistMode(mode) { try { await api('Settings/save', { event_whitelist_mode: mode }); eventUsersWhitelistMode.value = mode; flash('Mode whitelist diperbarui.') } catch (e) { error.value = e.message } }
async function setEventUserApproval(u, approved) { try { await api(`EventUsers/set-approval/${u.id}`, { approved }); await loadEventUsers(); if (eventUserDetail.value && eventUserDetail.value.id === u.id) await openEventUser({ id: u.id }); flash(approved ? 'User disetujui.' : 'Persetujuan dibatalkan.') } catch (e) { error.value = e.message } }
async function openEventUser(row) { eventUserModal.value = true; eventUserDetail.value = null; eventUserLoading.value = true; try { const d = await api(`EventUsers/show/${row.id}`); eventUserDetail.value = d; eventUserLimits.value = { max_events: d.max_events ?? '', max_photos_per_event: d.max_photos_per_event ?? '' } } catch (e) { error.value = e.message } finally { eventUserLoading.value = false } }
function closeEventUser() { eventUserModal.value = false; eventUserDetail.value = null }
async function saveEventUserLimits() { if (!eventUserDetail.value) return; eventUserSaving.value = true; try { await api(`EventUsers/save-limits/${eventUserDetail.value.id}`, eventUserLimits.value); await openEventUser({ id: eventUserDetail.value.id }); await loadEventUsers(); flash('Limit disimpan.') } catch (e) { error.value = e.message } finally { eventUserSaving.value = false } }
async function toggleEventUserStatus() { if (!eventUserDetail.value) return; const next = eventUserDetail.value.status === 'blocked' ? 'active' : 'blocked'; if (!window.confirm(next === 'blocked' ? 'Blokir user event ini?' : 'Aktifkan kembali user event ini?')) return; eventUserSaving.value = true; try { await api(`EventUsers/update-status/${eventUserDetail.value.id}`, { status: next }); await openEventUser({ id: eventUserDetail.value.id }); await loadEventUsers(); flash('Status diperbarui.') } catch (e) { error.value = e.message } finally { eventUserSaving.value = false } }

// ---- Uploads ----
const uploads = ref([])
const uploadLoading = ref(false)
const uploadSearch = ref('')
async function loadUploads() { uploadLoading.value = true; try { uploads.value = (await api('Uploads/index')).items || [] } catch (e) { error.value = e.message } finally { uploadLoading.value = false } }
const filteredUploads = computed(() => { const q = uploadSearch.value.trim().toLowerCase(); if (!q) return uploads.value; return uploads.value.filter((u) => (u.original_name || '').toLowerCase().includes(q) || (u.customer_name || '').toLowerCase().includes(q) || (u.product_name || '').toLowerCase().includes(q)) })
function formatSize(bytes) { const b = Number(bytes) || 0; if (b < 1024) return `${b} B`; if (b < 1048576) return `${(b / 1024).toFixed(0)} KB`; return `${(b / 1048576).toFixed(1)} MB` }
async function removeUpload(row) { if (!window.confirm(`Hapus file "${row.original_name}"?`)) return; try { await api(`Uploads/remove/${row.id}`, {}); await loadUploads(); flash('Upload dihapus.') } catch (e) { error.value = e.message } }

// ---- Dashboard ----
const dash = ref({})
const dashLoading = ref(false)
async function loadDashboard() { dashLoading.value = true; try { dash.value = await api('Dashboard/index') } catch (e) { error.value = e.message } finally { dashLoading.value = false } }
// ---- Settings ----
const settings = ref({ store_name: '', wa_number: '', origin_name: '', origin_contact_phone: '', origin_address: '', postal_code: '', couriers: '' })
const settingsSaving = ref(false)
async function loadSettings() { try { const d = await api('Settings/index'); settings.value = Object.assign({}, settings.value, d) } catch (e) { error.value = e.message } }
async function saveSettings() { settingsSaving.value = true; try { await api('Settings/save', settings.value); flash('Pengaturan disimpan.') } catch (e) { error.value = e.message } finally { settingsSaving.value = false } }

onMounted(async () => { try { user.value = await api('Auth/me'); view.value = 'dashboard'; await loadDashboard() } catch {} })
</script>

<template>
  <div v-if="!user" class="auth-shell">
    <form class="auth-card" @submit.prevent="login">
      <p class="eyebrow">VITA PICTURA</p>
      <h1>Masuk admin</h1>
      <p class="muted">Panel operasional pesanan dan produksi.</p>
      <input v-model="form.email" required type="email" placeholder="Email admin" autocomplete="username">
      <input v-model="form.password" required type="password" placeholder="Password" autocomplete="current-password">
      <p v-if="error" class="error">{{ error }}</p>
      <button class="primary" type="submit" :disabled="busy">{{ busy ? 'Memproses…' : 'Masuk' }}</button>
    </form>
  </div>

  <div v-else class="admin-layout">
    <a class="skip-link" href="#main">Lewati ke konten</a>
    <aside class="sidebar" :class="{ 'is-open': navOpen }">
      <div class="sidebar__brand">
        <span class="brand-mark" aria-hidden="true">VP</span>
        <div>
          <strong>Vita Pictura</strong>
          <small>Panel Operasional</small>
        </div>
      </div>
      <nav class="sidebar__nav" aria-label="Navigasi admin">
        <button v-for="item in navItems" :key="item.key" class="nav-item" type="button" :class="{ 'is-active': view === item.key }" :aria-current="view === item.key ? 'page' : null" @click="switchView(item.key)">{{ item.label }}</button>
      </nav>
      <div class="sidebar__user">
        <button class="profile-trigger" type="button" :aria-expanded="profileOpen" aria-haspopup="menu" @click="profileOpen = !profileOpen">
          <span class="avatar" aria-hidden="true">{{ user.name.charAt(0).toUpperCase() }}</span>
          <span class="profile-trigger__meta">
            <strong>{{ firstName }}</strong>
            <small>{{ user.role }}</small>
          </span>
          <svg class="caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
        </button>
        <div v-if="profileOpen" class="profile-dropdown" role="menu">
          <button class="profile-dropdown__item" type="button" role="menuitem" @click="openChangePassword">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <span>Ganti Password</span>
          </button>
          <button class="profile-dropdown__item profile-dropdown__item--danger" type="button" role="menuitem" @click="logout">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/></svg>
            <span>Keluar</span>
          </button>
        </div>
      </div>
    </aside>
    <div v-if="navOpen" class="scrim" @click="navOpen = false"></div>
    <div v-if="profileOpen" class="profile-scrim" @click="profileOpen = false"></div>

    <div id="main" tabindex="-1" class="admin-main">
      <header class="topbar">
        <button class="menu-btn" type="button" aria-label="Buka menu" @click="navOpen = !navOpen">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
        </button>
        <h2>{{ currentTitle }}</h2>
        <button v-if="view === 'orders'" class="ghost--sm" type="button" :disabled="loading" @click="loadOrders">Muat ulang</button>
        <button v-else-if="view === 'dashboard'" class="ghost--sm" type="button" :disabled="dashLoading" @click="loadDashboard">Muat ulang</button>
        <button v-else-if="view === 'categories'" class="primary primary--sm" type="button" @click="openCategory(null)">+ Tambah kategori</button>
        <button v-else-if="view === 'products'" class="primary primary--sm" type="button" @click="openProduct(null)">+ Tambah produk</button>
        <button v-else-if="view === 'customers'" class="ghost--sm" type="button" :disabled="custLoading" @click="loadCustomers">Muat ulang</button>
        <button v-else-if="view === 'eventusers'" class="ghost--sm" type="button" :disabled="eventUsersLoading" @click="loadEventUsers">Muat ulang</button>
        <button v-else-if="view === 'uploads'" class="ghost--sm" type="button" :disabled="uploadLoading" @click="loadUploads">Muat ulang</button>
      </header>

      <div class="alerts" aria-live="polite">
        <div v-if="error" class="alert alert--error" role="alert">
          <span>{{ error }}</span>
          <button type="button" aria-label="Tutup peringatan" @click="error = ''">×</button>
        </div>
        <div v-if="notice" class="alert alert--success" role="status">
          <span>{{ notice }}</span>
          <button type="button" aria-label="Tutup notifikasi" @click="notice = ''">×</button>
        </div>
      </div>

      <!-- Dashboard -->
      <template v-if="view === 'dashboard'">
        <section class="stats">
          <article class="stat"><span>Pesanan</span><strong>{{ dash.ordersTotal || 0 }}</strong></article>
          <article class="stat"><span>Perlu diproses</span><strong>{{ dash.processing || 0 }}</strong></article>
          <article class="stat"><span>Menunggu bayar</span><strong>{{ dash.pending || 0 }}</strong></article>
          <article class="stat"><span>Pendapatan</span><strong>{{ rupiah.format(dash.revenue || 0) }}</strong></article>
          <article class="stat"><span>Produk</span><strong>{{ dash.products || 0 }}</strong></article>
          <article class="stat"><span>Pelanggan</span><strong>{{ dash.customers || 0 }}</strong></article>
        </section>

        <section class="panel">
          <h3 class="detail-sub">Pesanan terbaru</h3>
          <p v-if="!dash.recentOrders || !dash.recentOrders.length" class="muted">Belum ada pesanan.</p>
          <div v-else class="table-wrap">
            <table class="orders-table">
              <thead><tr><th>No.</th><th>Pelanggan</th><th>Tanggal</th><th>Total</th><th>Status</th></tr></thead>
              <tbody><tr v-for="o in dash.recentOrders" :key="o.id">
                <td data-label="No.">{{ o.order_number }}</td>
                <td data-label="Pelanggan">{{ o.customer_name }}</td>
                <td data-label="Tanggal">{{ formatDate(o.created_at) }}</td>
                <td data-label="Total">{{ rupiah.format(o.total) }}</td>
                <td data-label="Status"><span class="status" :class="`status--${o.status}`">{{ o.status }}</span></td>
              </tr></tbody>
            </table>
          </div>
        </section>

        <section class="panel">
          <h3 class="detail-sub">Pelanggan baru</h3>
          <p v-if="!dash.newCustomers || !dash.newCustomers.length" class="muted">Belum ada pelanggan.</p>
          <div v-else class="table-wrap">
            <table class="orders-table">
              <thead><tr><th>Nama</th><th>Email</th><th>Terdaftar</th></tr></thead>
              <tbody><tr v-for="c in dash.newCustomers" :key="c.id">
                <td data-label="Nama">{{ c.full_name }}</td>
                <td data-label="Email">{{ c.email }}</td>
                <td data-label="Terdaftar">{{ formatDate(c.created_at) }}</td>
              </tr></tbody>
            </table>
          </div>
        </section>
      </template>

      <!-- Pesanan -->
      <template v-else-if="view === 'orders'">
        <section class="stats">
          <article class="stat"><span>Total</span><strong>{{ orders.length }}</strong></article>
          <article class="stat"><span>Perlu diproses</span><strong>{{ countBy('processing') }}</strong></article>
          <article class="stat"><span>Dikirim</span><strong>{{ countBy('shipped') }}</strong></article>
          <article class="stat"><span>Selesai</span><strong>{{ countBy('completed') }}</strong></article>
        </section>

        <div class="tabs">
          <button v-for="t in orderTabs" :key="t.key" type="button" class="tab" :class="{ 'is-active': statusFilter === t.key }" @click="statusFilter = t.key">{{ t.label }}<span v-if="orderCount(t.key)" class="tab__count">{{ orderCount(t.key) }}</span></button>
        </div>

        <section class="panel" :aria-busy="loading">
          <div class="panel__head">
            <input v-model="search" class="search" type="search" placeholder="Cari nomor pesanan atau nama…">
          </div>

          <div v-if="loading" class="skeleton-list" aria-hidden="true">
            <span v-for="n in 4" :key="n" class="skeleton skeleton--row"></span>
          </div>
          <p v-else-if="!filteredOrders.length" class="muted">Tidak ada pesanan yang cocok.</p>

          <div v-else class="table-wrap">
            <table class="orders-table">
              <thead>
                <tr><th>No. Pesanan</th><th>Pelanggan</th><th>Tanggal</th><th>Total</th><th>Bayar</th><th>Status</th><th></th></tr>
              </thead>
              <tbody>
                <tr v-for="order in filteredOrders" :key="order.id">
                  <td data-label="No. Pesanan"><strong>{{ order.order_number }}</strong><small v-if="order.tracking_number">Resi: {{ order.tracking_number }}</small></td>
                  <td data-label="Pelanggan">{{ order.customer_name }}</td>
                  <td data-label="Tanggal">{{ formatDate(order.created_at) }}</td>
                  <td data-label="Total">{{ rupiah.format(order.total) }}</td>
                  <td data-label="Bayar"><span class="status" :class="`status--${order.payment_status || 'unpaid'}`">{{ order.payment_status || 'unpaid' }}</span></td>
                  <td data-label="Status">
                    <select v-if="order.status === 'processing' || order.status === 'cancelled'" :value="order.status" :disabled="savingId === order.id" @change="update(order, $event.target.value)">
                      <option value="processing">Processing</option>
                      <option value="cancelled">Cancelled</option>
                    </select>
                    <span v-else class="status" :class="`status--${order.status}`">{{ order.status }}</span>
                  </td>
                  <td data-label="Aksi">
                    <div class="row-actions">
                      <button class="btn-action btn-action--detail" type="button" @click="openOrder(order)">Detail</button>
                      <button v-if="order.status === 'processing'" class="btn-action btn-action--ship" type="button" @click="openShip(order)">Kirim</button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>
      </template>

      <!-- Kategori -->
      <template v-else-if="view === 'categories'">
        <section class="panel" :aria-busy="catLoading">
          <div v-if="catLoading" class="skeleton-list" aria-hidden="true">
            <span v-for="n in 4" :key="n" class="skeleton skeleton--row"></span>
          </div>
          <p v-else-if="!categories.length" class="muted">Belum ada kategori.</p>
          <div v-else class="table-wrap">
            <table class="orders-table">
              <thead>
                <tr><th>Nama</th><th>Slug</th><th>Produk</th><th>Status</th><th>Unggulan</th><th></th></tr>
              </thead>
              <tbody>
                <tr v-for="item in categories" :key="item.id">
                  <td data-label="Nama"><strong>{{ item.name }}</strong><small v-if="item.description">{{ item.description }}</small></td>
                  <td data-label="Slug"><code>{{ item.slug }}</code></td>
                  <td data-label="Produk">{{ item.product_count }}</td>
                  <td data-label="Status"><span class="status" :class="`status--${item.status === 'published' ? 'completed' : 'unpaid'}`">{{ item.status }}</span></td>
                  <td data-label="Unggulan">{{ item.is_featured ? 'Ya' : '—' }}</td>
                  <td data-label="Aksi">
                    <span class="row-actions">
                      <button class="link" type="button" @click="openCategory(item)">Edit</button>
                      <button class="link-danger" type="button" @click="removeCategory(item)">Hapus</button>
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>
      </template>

      <!-- Produk -->
      <template v-else-if="view === 'products'">
        <section class="panel" :aria-busy="prodLoading">
          <div class="panel__head">
            <input v-model="prodSearch" class="search" type="search" placeholder="Cari produk…">
            <select v-model="prodCategory">
              <option value="">Semua kategori</option>
              <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
            <select v-model="prodStatus">
              <option value="">Semua status</option>
              <option value="published">Published</option>
              <option value="draft">Draft</option>
              <option value="archived">Archived</option>
            </select>
          </div>

          <div v-if="prodLoading" class="skeleton-list" aria-hidden="true">
            <span v-for="n in 4" :key="n" class="skeleton skeleton--row"></span>
          </div>
          <p v-else-if="!filteredProducts.length" class="muted">Tidak ada produk yang cocok.</p>
          <div v-else class="table-wrap">
            <table class="orders-table">
              <thead>
                <tr><th>Produk</th><th>Kategori</th><th>Harga</th><th>Status</th><th>Unggulan</th><th></th></tr>
              </thead>
              <tbody>
                <tr v-for="p in filteredProducts" :key="p.id">
                  <td data-label="Produk">
                    <div class="prod-cell">
                      <span class="prod-thumb"><img v-if="p.cover_image_url" :src="mediaUrl(p.cover_image_url)" :alt="p.name"></span>
                      <div><strong>{{ p.name }}</strong><small>{{ p.slug }}</small></div>
                    </div>
                  </td>
                  <td data-label="Kategori">{{ p.category_name || '—' }}</td>
                  <td data-label="Harga">{{ rupiah.format(p.base_price) }}</td>
                  <td data-label="Status"><span class="status" :class="`status--${p.status === 'published' ? 'completed' : 'unpaid'}`">{{ p.status }}</span></td>
                  <td data-label="Unggulan">{{ p.is_featured ? 'Ya' : '—' }}</td>
                  <td data-label="Aksi">
                    <span class="row-actions">
                      <button class="link" type="button" @click="openProduct(p)">Edit</button>
                      <button class="link-danger" type="button" @click="removeProduct(p)">Hapus</button>
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>
      </template>

      <!-- Media library -->
      <template v-else-if="view === 'media'">
        <MediaLibrary />
      </template>

      <!-- Pelanggan -->
      <template v-else-if="view === 'customers'">
        <section class="panel" :aria-busy="custLoading">
          <div class="panel__head">
            <input v-model="custSearch" class="search" type="search" placeholder="Cari nama / email / telepon…">
          </div>
          <div v-if="custLoading" class="skeleton-list" aria-hidden="true"><span v-for="n in 4" :key="n" class="skeleton skeleton--row"></span></div>
          <p v-else-if="!filteredCustomers.length" class="muted">Tidak ada pelanggan.</p>
          <div v-else class="table-wrap">
            <table class="orders-table">
              <thead><tr><th>Pelanggan</th><th>Telepon</th><th>Pesanan</th><th>Status</th><th>Terdaftar</th><th></th></tr></thead>
              <tbody>
                <tr v-for="c in filteredCustomers" :key="c.id">
                  <td data-label="Pelanggan"><strong>{{ c.full_name }}</strong><small>{{ c.email }}</small></td>
                  <td data-label="Telepon">{{ c.phone || '—' }}</td>
                  <td data-label="Pesanan">{{ c.order_count }}</td>
                  <td data-label="Status"><span class="status" :class="c.status === 'active' ? 'status--paid' : 'status--expired'">{{ c.status }}</span></td>
                  <td data-label="Terdaftar">{{ formatDate(c.created_at) }}</td>
                  <td data-label="Aksi"><button class="link" type="button" @click="openCustomer(c)">Detail</button></td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>
      </template>

      <!-- Event user -->
      <template v-else-if="view === 'eventusers'">
        <section class="stats">
          <article class="stat"><span>Total user event</span><strong>{{ eventUsers.length }}</strong></article>
          <article class="stat"><span>Default event</span><strong>{{ eventUsersDefaults.maxEvents }}</strong></article>
          <article class="stat"><span>Default foto/event</span><strong>{{ eventUsersDefaults.maxPhotos }}</strong></article>
        </section>
        <section class="panel">
          <div class="panel__head">
            <span class="muted small">Whitelist aktif — hanya user yang <strong>disetujui</strong> yang boleh membuat event. Setujui lewat tombol di kolom <strong>Approval</strong>.</span>
          </div>
        </section>
        <section class="panel" :aria-busy="eventUsersLoading">
          <div v-if="eventUsersLoading" class="skeleton-list" aria-hidden="true"><span v-for="n in 4" :key="n" class="skeleton skeleton--row"></span></div>
          <p v-else-if="!eventUsers.length" class="muted">Belum ada user event.</p>
          <div v-else class="table-wrap">
            <table class="orders-table">
              <thead><tr><th>User</th><th>Event</th><th>Foto</th><th>Saldo</th><th>Limit</th><th>Status</th><th>Approval</th><th></th></tr></thead>
              <tbody>
                <tr v-for="u in eventUsers" :key="u.id">
                  <td data-label="User"><strong>{{ u.full_name }}</strong><small>{{ u.email }}</small></td>
                  <td data-label="Event">{{ u.event_count }}</td>
                  <td data-label="Foto">{{ u.photo_count }}</td>
                  <td data-label="Saldo">{{ rupiah.format(u.balance) }}</td>
                  <td data-label="Limit"><small>Event: {{ u.max_events ?? eventUsersDefaults.maxEvents }} · Foto: {{ u.max_photos_per_event ?? eventUsersDefaults.maxPhotos }}</small></td>
                  <td data-label="Status"><span class="status" :class="u.status === 'active' ? 'status--paid' : 'status--expired'">{{ u.status }}</span></td>
                  <td data-label="Approval">
                    <button class="link" type="button" @click="setEventUserApproval(u, !u.approved)">{{ u.approved ? 'Batalkan' : 'Setujui' }}</button>
                  </td>
                  <td data-label="Aksi"><button class="link" type="button" @click="openEventUser(u)">Detail</button></td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>
      </template>

      <!-- Upload desain -->
      <template v-else-if="view === 'uploads'">
        <section class="panel" :aria-busy="uploadLoading">
          <div class="panel__head">
            <input v-model="uploadSearch" class="search" type="search" placeholder="Cari file / pelanggan / produk…">
          </div>
          <div v-if="uploadLoading" class="skeleton-list" aria-hidden="true"><span v-for="n in 4" :key="n" class="skeleton skeleton--row"></span></div>
          <p v-else-if="!filteredUploads.length" class="muted">Belum ada upload.</p>
          <div v-else class="table-wrap">
            <table class="orders-table">
              <thead><tr><th>File</th><th>Pelanggan</th><th>Produk</th><th>Ukuran</th><th>Tanggal</th><th></th></tr></thead>
              <tbody>
                <tr v-for="u in filteredUploads" :key="u.id">
                  <td data-label="File"><a :href="u.url" target="_blank" rel="noopener"><strong>{{ u.original_name }}</strong></a><small>{{ u.mime_type }}</small></td>
                  <td data-label="Pelanggan">{{ u.customer_name || '—' }}</td>
                  <td data-label="Produk">{{ u.product_name || '—' }}</td>
                  <td data-label="Ukuran">{{ formatSize(u.size_bytes) }}</td>
                  <td data-label="Tanggal">{{ formatDate(u.created_at) }}</td>
                  <td data-label="Aksi"><span class="row-actions"><a class="link" :href="u.url" target="_blank" rel="noopener">Buka</a><button class="link-danger" type="button" @click="removeUpload(u)">Hapus</button></span></td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>
      </template>

      <!-- Pengaturan -->
      <template v-else-if="view === 'settings'">
        <section class="panel">
          <form class="settings-form" @submit.prevent="saveSettings">
            <label class="field"><span>Nama toko</span><input v-model="settings.store_name" placeholder="Vita Pictura"></label>
            <div class="field-row">
              <label class="field"><span>Nomor WhatsApp</span><input v-model="settings.wa_number" placeholder="6285..."></label>
              <label class="field"><span>Kode pos</span><input v-model="settings.postal_code"></label>
            </div>
            <div class="field-row">
              <label class="field"><span>Nama pengirim</span><input v-model="settings.origin_name"></label>
              <label class="field"><span>Telepon pengirim</span><input v-model="settings.origin_contact_phone"></label>
            </div>
            <label class="field"><span>Alamat pengirim</span><textarea v-model="settings.origin_address" rows="2"></textarea></label>
            <label class="field"><span>Kurir (pisah koma)</span><input v-model="settings.couriers" placeholder="jne,jnt,sicepat"></label>
            <div class="modal-actions">
              <button class="primary" type="submit" :disabled="settingsSaving">{{ settingsSaving ? 'Menyimpan…' : 'Simpan pengaturan' }}</button>
            </div>
          </form>
        </section>
      </template>
    </div>
  </div>

  <!-- Modal kategori -->
  <div v-if="catModal" class="modal-overlay" role="dialog" aria-modal="true" aria-label="Form kategori" @click.self="closeCategory">
    <form class="modal-card" @submit.prevent="saveCategory">
      <div class="modal-head">
        <h3>{{ catForm.id ? 'Edit kategori' : 'Tambah kategori' }}</h3>
        <button class="modal-x" type="button" aria-label="Tutup" @click="closeCategory">×</button>
      </div>
      <label class="field"><span>Nama</span><input v-model="catForm.name" required placeholder="Contoh: Cetak" @input="onCategoryName"></label>
      <label class="field"><span>Slug (opsional)</span><input v-model="catForm.slug" placeholder="otomatis dari nama"></label>
      <label class="field"><span>Deskripsi</span><textarea v-model="catForm.description" rows="2" placeholder="Deskripsi singkat"></textarea></label>
      <label class="field"><span>Gambar URL (opsional)</span><input v-model="catForm.image_url" placeholder="/uploads/... atau https://..."></label>
      <div class="field-row">
        <label class="field"><span>Urutan</span><input v-model.number="catForm.sort_order" type="number"></label>
        <label class="field"><span>Status</span>
          <select v-model="catForm.status">
            <option value="published">Published</option>
            <option value="draft">Draft</option>
            <option value="archived">Archived</option>
          </select>
        </label>
      </div>
      <label class="check"><input v-model="catForm.is_featured" type="checkbox"> Tampilkan sebagai unggulan</label>
      <div class="modal-actions">
        <button class="ghost--sm" type="button" @click="closeCategory">Batal</button>
        <button class="primary" type="submit" :disabled="catSaving">{{ catSaving ? 'Menyimpan…' : 'Simpan' }}</button>
      </div>
    </form>
  </div>

  <!-- Modal produk -->
  <div v-if="prodModal" class="modal-overlay" role="dialog" aria-modal="true" aria-label="Form produk" @click.self="closeProduct">
    <form class="modal-card modal-card--wide product-form" @submit.prevent="saveProduct">
      <div class="modal-head">
        <h3>{{ prodForm.id ? 'Edit produk' : 'Tambah produk' }}</h3>
        <button class="modal-x" type="button" aria-label="Tutup" @click="closeProduct">×</button>
      </div>
      <div class="product-form-grid">
        <label class="field span-2"><span>Nama</span><input v-model="prodForm.name" required placeholder="Contoh: Cetak Foto" @input="onProductName"></label>
        <label class="field"><span>Slug (opsional)</span><input v-model="prodForm.slug" placeholder="otomatis dari nama"></label>
        <label class="field"><span>Kategori</span>
          <select v-model="prodForm.category_id">
            <option value="">— Tanpa kategori —</option>
            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </label>
        <label class="field"><span>Harga dasar (Rp)</span><input v-model.number="prodForm.base_price" type="number" min="0"></label>
        <label class="field"><span>Berat (gram)</span><input v-model.number="prodForm.weight_grams" type="number" min="0"></label>
        <label class="field"><span>Panjang (mm)</span><input v-model="prodForm.length_mm" type="number" min="0"></label>
        <label class="field"><span>Lebar (mm)</span><input v-model="prodForm.width_mm" type="number" min="0"></label>
        <label class="field"><span>Tinggi (mm)</span><input v-model="prodForm.height_mm" type="number" min="0"></label>
        <label class="field"><span>Status</span>
          <select v-model="prodForm.status">
            <option value="published">Published</option>
            <option value="draft">Draft</option>
            <option value="archived">Archived</option>
          </select>
        </label>
        <label class="check check--end"><input v-model="prodForm.is_featured" type="checkbox"> Unggulan</label>
        <label class="field span-2"><span>Deskripsi singkat</span><input v-model="prodForm.short_description"></label>
        <div class="field span-2">
          <span>Gambar utama</span>
          <div v-if="prodForm.cover_image_url" class="media-field-preview"><img :src="mediaUrl(prodForm.cover_image_url)" alt="Pratinjau gambar utama"></div>
          <p v-else class="muted media-field-empty">Belum ada gambar utama. Pilih dari Media.</p>
          <div class="media-gallery-actions">
            <button class="ghost--sm" type="button" @click="openMediaPicker('main')">{{ prodForm.cover_image_url ? 'Ganti gambar' : 'Pilih dari Media' }}</button>
            <button v-if="prodForm.cover_image_url" class="ghost--sm" type="button" @click="prodForm.cover_image_url = ''">Hapus</button>
          </div>
        </div>
        <div v-if="prodForm.id" class="field span-2">
          <span>Galeri media</span>
          <div v-if="prodMedia.length" class="media-grid">
            <div v-for="m in prodMedia" :key="m.id" class="media-item" :class="{ 'is-cover': m.url === prodForm.cover_image_url }">
              <img :src="mediaUrl(m.url)" alt="">
              <div class="media-tools">
                <button type="button" class="media-btn" :disabled="m.url === prodForm.cover_image_url" title="Jadikan cover" @click="setCover(m)">★</button>
                <button type="button" class="media-btn" title="Atur suffix gambar varian" @click="setMediaSuffix(m)">S</button>
                <button type="button" class="media-btn" title="Naik" @click="moveMedia(m, -1)">↑</button>
                <button type="button" class="media-btn" title="Turun" @click="moveMedia(m, 1)">↓</button>
                <button type="button" class="media-btn media-btn--danger" title="Hapus" @click="removeMedia(m)">×</button>
              </div>
            </div>
          </div>
          <p v-else class="muted media-field-empty">Belum ada gambar galeri.</p>
          <div class="media-gallery-actions">
            <button class="ghost--sm" type="button" @click="addLibraryMedia">Pilih dari Media</button>
          </div>
          <small class="muted">{{ prodMedia.length }} media · tanda ★ = cover</small>
        </div>
        <fieldset class="product-files span-2">
          <legend>File Mal / Template</legend>
          <label class="check"><input v-model="prodForm.perlu_file" type="checkbox"> Produk memerlukan file dari pelanggan (pengiriman file)</label>
          <p class="muted">File mal/template yang bisa diunduh pelanggan (opsional). Pilih dari Media.</p>
          <div v-if="prodForm.mal.length" class="mal-list">
            <div v-for="(item, i) in prodForm.mal" :key="`${item.url}-${i}`" class="mal-item">
              <span class="mal-item__name">{{ item.name }}</span>
              <a class="mal-item__link" :href="mediaUrl(item.url)" target="_blank" rel="noopener">Buka</a>
              <button class="mal-item__remove" type="button" @click="prodForm.mal.splice(i, 1)">Hapus</button>
            </div>
          </div>
          <p v-else class="muted">Belum ada file mal.</p>
          <div class="media-gallery-actions">
            <button class="ghost--sm" type="button" @click="openMediaPicker('mal')">Tambah dari Media</button>
          </div>
        </fieldset>
        <div v-if="prodForm.id" class="field span-2">
          <span>Varian</span>
          <button class="ghost--sm variant-open" type="button" @click="openVariants">Kelola varian (grup &amp; nilai)</button>
        </div>
        <div v-if="prodForm.id" class="field span-2">
          <span>Tab deskripsi</span>
          <button class="ghost--sm variant-open" type="button" @click="openTabs">Kelola tab deskripsi (judul &amp; isi)</button>
        </div>
      </div>
      <div class="modal-actions">
        <button class="ghost--sm" type="button" @click="closeProduct">Batal</button>
        <button class="primary" type="submit" :disabled="prodSaving">{{ prodSaving ? 'Menyimpan…' : 'Simpan' }}</button>
      </div>
    </form>
  </div>

  <!-- Modal varian -->
  <div v-if="variantModal" class="modal-overlay modal-overlay--top" role="dialog" aria-modal="true" aria-label="Kelola varian" @click.self="closeVariants">
    <div class="modal-card modal-card--wide">
      <div class="modal-head">
        <h3>Varian — {{ variantProduct.name }}</h3>
        <button class="modal-x" type="button" aria-label="Tutup" @click="closeVariants">×</button>
      </div>
      <div class="variant-toolbar">
        <button class="primary primary--sm" type="button" @click="openGroup(null, null)">+ Grup utama</button>
      </div>
      <div v-if="variantLoading" class="skeleton-list" aria-hidden="true"><span v-for="n in 3" :key="n" class="skeleton skeleton--row"></span></div>
      <p v-else-if="!variants.length" class="muted">Belum ada varian. Tambahkan grup utama.</p>
      <template v-else>
        <div v-for="group in level1Groups" :key="group.id" class="variant-group">
          <div class="variant-group__head">
            <strong>{{ group.name }}</strong>
            <span class="status" :class="group.is_required ? 'status--processing' : 'status--unpaid'">{{ group.is_required ? 'Wajib' : 'Opsional' }}</span>
            <div class="row-actions">
              <button class="link" type="button" @click="openGroup(group)">Edit</button>
              <button class="link-danger" type="button" @click="removeGroup(group)">Hapus</button>
            </div>
          </div>
          <table class="orders-table variant-table">
            <thead><tr><th>Nilai</th><th>Harga +</th><th>Suffix</th><th>Aktif</th><th></th></tr></thead>
            <tbody>
              <tr v-for="v in group.values" :key="v.id">
                <td data-label="Nilai">{{ v.name }}</td>
                <td data-label="Harga +">{{ rupiah.format(v.price_delta) }}</td>
                <td data-label="Suffix">{{ v.image_suffix || '—' }}</td>
                <td data-label="Aktif">{{ v.is_active ? 'Ya' : 'Tidak' }}</td>
                <td data-label="Aksi"><span class="row-actions"><button class="link" type="button" @click="openValue(group, v)">Edit</button><button class="link-danger" type="button" @click="removeValue(v)">Hapus</button></span></td>
              </tr>
            </tbody>
          </table>
          <div class="variant-actions">
            <button class="ghost--sm" type="button" @click="openValue(group, null)">+ Nilai</button>
            <button class="ghost--sm" type="button" @click="openGroup(null, group.id)">+ Sub-grup</button>
          </div>

          <div v-for="sub in subGroups(group)" :key="sub.id" class="variant-sub">
            <div class="variant-group__head">
              <strong>{{ sub.name }}</strong>
              <span class="status status--unpaid">Turunan</span>
              <div class="row-actions">
                <button class="link" type="button" @click="openGroup(sub)">Edit</button>
                <button class="link-danger" type="button" @click="removeGroup(sub)">Hapus</button>
              </div>
            </div>
            <table class="orders-table variant-table">
              <thead><tr><th>Nilai</th><th>Induk</th><th>Harga +</th><th>Suffix</th><th>Aktif</th><th></th></tr></thead>
              <tbody>
                <tr v-for="v in sub.values" :key="v.id">
                  <td data-label="Nilai">{{ v.name }}</td>
                  <td data-label="Induk">{{ valueName(v.parent_value_id) || '—' }}</td>
                  <td data-label="Harga +">{{ rupiah.format(v.price_delta) }}</td>
                  <td data-label="Suffix">{{ v.image_suffix || '—' }}</td>
                  <td data-label="Aktif">{{ v.is_active ? 'Ya' : 'Tidak' }}</td>
                  <td data-label="Aksi"><span class="row-actions"><button class="link" type="button" @click="openValue(sub, v)">Edit</button><button class="link-danger" type="button" @click="removeValue(v)">Hapus</button></span></td>
                </tr>
              </tbody>
            </table>
            <div class="variant-actions"><button class="ghost--sm" type="button" @click="openValue(sub, null)">+ Nilai</button></div>
          </div>
        </div>
      </template>
    </div>
  </div>

  <!-- Modal grup varian -->
  <div v-if="groupModal" class="modal-overlay modal-overlay--top2" role="dialog" aria-modal="true" aria-label="Form grup varian" @click.self="groupModal = false">
    <form class="modal-card" @submit.prevent="saveGroup">
      <div class="modal-head"><h3>{{ groupForm.id ? 'Edit grup' : 'Tambah grup' }}</h3><button class="modal-x" type="button" aria-label="Tutup" @click="groupModal = false">×</button></div>
      <label class="field"><span>Nama grup</span><input v-model="groupForm.name" required placeholder="Contoh: Ukuran"></label>
      <div class="field-row">
        <label class="field"><span>Level</span>
          <select v-model.number="groupForm.group_level">
            <option :value="1">Utama</option>
            <option :value="2">Turunan</option>
          </select>
        </label>
        <label class="field"><span>Grup induk</span>
          <select v-model="groupForm.parent_group_id" :disabled="groupForm.group_level !== 2">
            <option value="">—</option>
            <option v-for="g in level1Groups" :key="g.id" :value="g.id">{{ g.name }}</option>
          </select>
        </label>
      </div>
      <div class="field-row">
        <label class="field"><span>Urutan</span><input v-model.number="groupForm.sort_order" type="number"></label>
        <label class="check check--end"><input v-model="groupForm.is_required" type="checkbox"> Wajib dipilih</label>
      </div>
      <div class="modal-actions"><button class="ghost--sm" type="button" @click="groupModal = false">Batal</button><button class="primary" type="submit" :disabled="groupSaving">{{ groupSaving ? 'Menyimpan…' : 'Simpan' }}</button></div>
    </form>
  </div>

  <!-- Modal nilai varian -->
  <div v-if="valueModal" class="modal-overlay modal-overlay--top2" role="dialog" aria-modal="true" aria-label="Form nilai varian" @click.self="valueModal = false">
    <form class="modal-card" @submit.prevent="saveValue">
      <div class="modal-head"><h3>{{ valueForm.id ? 'Edit nilai' : 'Tambah nilai' }}</h3><button class="modal-x" type="button" aria-label="Tutup" @click="valueModal = false">×</button></div>
      <label class="field"><span>Nama nilai</span><input v-model="valueForm.name" required placeholder="Contoh: 10x15cm"></label>
      <label class="field"><span>Harga tambahan (Rp)</span><input v-model.number="valueForm.price_delta" type="number"></label>
      <div class="field">
        <span>Gambar varian (pilih dari galeri produk)</span>
        <div class="suffix-picker">
          <button type="button" class="suffix-option" :class="{ 'is-active': !valueForm.image_suffix }" @click="valueForm.image_suffix = ''">
            <span class="suffix-option__thumb suffix-option__thumb--none">—</span>
            <span class="suffix-option__label">Tanpa</span>
          </button>
          <button v-for="opt in gallerySuffixOptions" :key="opt.suffix" type="button" class="suffix-option" :class="{ 'is-active': valueForm.image_suffix === opt.suffix }" :title="opt.key" @click="valueForm.image_suffix = opt.suffix">
            <img class="suffix-option__thumb" :src="mediaUrl(opt.url)" :alt="opt.suffix">
            <span class="suffix-option__label">{{ opt.suffix }}</span>
          </button>
        </div>
        <small v-if="!gallerySuffixOptions.length" class="muted">Belum ada gambar galeri ber-suffix. Atur lewat tombol "S" pada Galeri media produk.</small>
      </div>
      <label class="field"><span>Image suffix (manual)</span><input v-model="valueForm.image_suffix" placeholder="mis: full_logo"></label>
      <div class="field-row field-row--3">
        <label class="field"><span>Berat (g)</span><input v-model.number="valueForm.weight_delta_grams" type="number"></label>
        <label class="field"><span>Panjang (mm)</span><input v-model.number="valueForm.length_delta_mm" type="number"></label>
        <label class="field"><span>Lebar (mm)</span><input v-model.number="valueForm.width_delta_mm" type="number"></label>
      </div>
      <label class="field"><span>Nilai induk (untuk grup turunan)</span>
        <select v-model="valueForm.parent_value_id">
          <option value="">—</option>
          <template v-for="g in level1Groups" :key="g.id">
            <option v-for="v in g.values" :key="v.id" :value="v.id">{{ g.name }}: {{ v.name }}</option>
          </template>
        </select>
      </label>
      <div class="field-row">
        <label class="field"><span>Urutan</span><input v-model.number="valueForm.sort_order" type="number"></label>
        <label class="check check--end"><input v-model="valueForm.is_active" type="checkbox"> Aktif</label>
      </div>
      <div class="modal-actions"><button class="ghost--sm" type="button" @click="valueModal = false">Batal</button><button class="primary" type="submit" :disabled="valueSaving">{{ valueSaving ? 'Menyimpan…' : 'Simpan' }}</button></div>
    </form>
  </div>

  <!-- Modal detail pesanan -->
  <div v-if="orderModal" class="modal-overlay" role="dialog" aria-modal="true" aria-label="Detail pesanan" @click.self="closeOrder">
    <div class="modal-card modal-card--wide">
      <div class="modal-head">
        <h3 v-if="orderDetail">{{ orderDetail.order_number }} <span class="status" :class="`status--${orderDetail.status}`">{{ orderDetail.status }}</span></h3>
        <h3 v-else>Detail pesanan</h3>
        <button class="modal-x" type="button" aria-label="Tutup" @click="closeOrder">×</button>
      </div>
      <div v-if="orderLoading" class="skeleton-list" aria-hidden="true"><span v-for="n in 3" :key="n" class="skeleton skeleton--row"></span></div>
      <template v-else-if="orderDetail">
        <div class="detail-grid">
          <div><span>Pelanggan</span><strong>{{ orderDetail.customer_name }}</strong><small>{{ orderDetail.customer_email }}</small></div>
          <div><span>Tanggal</span><strong>{{ formatDateTime(orderDetail.created_at) }}</strong></div>
          <div><span>Subtotal</span><strong>{{ rupiah.format(orderDetail.subtotal) }}</strong></div>
          <div><span>Ongkir</span><strong>{{ rupiah.format(orderDetail.shipping_cost) }}</strong></div>
          <div v-if="orderDetail.discount_shipping > 0"><span>Diskon Ongkir</span><strong>-{{ rupiah.format(orderDetail.discount_shipping) }}</strong></div>
          <div v-if="orderDetail.discount_items > 0"><span>Diskon Belanja</span><strong>-{{ rupiah.format(orderDetail.discount_items) }}</strong></div>
          <div v-if="orderDetail.discount_promo > 0"><span>Diskon Promo</span><strong>-{{ rupiah.format(orderDetail.discount_promo) }}</strong></div>
          <div><span>Total</span><strong>{{ rupiah.format(orderDetail.total) }}</strong></div>
        </div>

        <h4 class="detail-sub">Item</h4>
        <div class="table-wrap">
          <table class="orders-table variant-table">
            <thead><tr><th>Produk</th><th>Pilihan</th><th>Qty</th><th>Harga</th><th>Total</th></tr></thead>
            <tbody>
              <tr v-for="it in orderDetail.items" :key="it.id">
                <td data-label="Produk">{{ it.product_name }}<small v-if="it.note" class="detail-note">Catatan: {{ it.note }}</small></td>
                <td data-label="Pilihan"><span v-for="s in it.selections" :key="s.valueId" class="detail-chip">{{ s.group }}: {{ s.value }}</span></td>
                <td data-label="Qty">{{ it.quantity }}</td>
                <td data-label="Harga">{{ rupiah.format(it.unit_price) }}</td>
                <td data-label="Total">{{ rupiah.format(it.total_price) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <h4 class="detail-sub">Penerima</h4>
        <div class="detail-recipient">
          <strong>{{ orderDetail.recipient.recipientName }}</strong>
          <span>{{ orderDetail.recipient.recipientPhone }}</span>
          <span>{{ orderDetail.recipient.addressLine }}</span>
          <span>{{ [orderDetail.recipient.areaName, orderDetail.recipient.postalCode].filter(Boolean).join(' · ') }}</span>
          <span v-if="orderDetail.recipient.notes">Catatan: {{ orderDetail.recipient.notes }}</span>
        </div>

        <h4 class="detail-sub">Pembayaran</h4>
        <div class="detail-grid">
          <div><span>Status</span><strong><span class="status" :class="`status--${orderDetail.payment?.status || 'unpaid'}`">{{ orderDetail.payment?.status || 'unpaid' }}</span></strong></div>
          <div><span>Metode</span><strong>{{ orderDetail.payment?.payment_type || '—' }}</strong></div>
          <div><span>Transaksi</span><strong>{{ orderDetail.payment?.transaction_id || '—' }}</strong></div>
          <div><span>Dibayar</span><strong>{{ formatDateTime(orderDetail.payment?.paid_at) }}</strong></div>
          <div v-if="orderDetail.payment?.expiry_time"><span>Batas Bayar</span><strong>{{ dueText(orderDetail.created_at, orderDetail.payment.expiry_time) }}</strong></div>
        </div>
        <div class="detail-actions-line">
          <button v-if="orderDetail.payment?.status !== 'paid'" class="ghost--sm" type="button" :disabled="savingOrder" @click="markPaid">Tandai lunas</button>
          <button v-if="['pending_payment','paid','processing'].includes(orderDetail.status)" class="link-danger" type="button" :disabled="savingOrder" @click="cancelOrder">Batalkan</button>
          <span v-if="orderDetail.admin_note" class="detail-note">Catatan: {{ orderDetail.admin_note }}</span>
        </div>

        <h4 class="detail-sub">Pengiriman</h4>
        <div class="detail-grid">
          <div><span>Kurir</span><strong>{{ orderDetail.courier_company ? `${orderDetail.courier_company} ${orderDetail.courier_type || ''}` : 'Jemput ke Toko' }}</strong><small v-if="orderDetail.courier_service">{{ orderDetail.courier_service }}</small></div>
          <div v-if="orderDetail.delivery?.tracking_number"><span>No. Resi</span><strong>{{ orderDetail.delivery.tracking_number }}</strong></div>
          <div v-if="orderDetail.delivery?.collection_method"><span>Metode</span><strong>{{ orderDetail.delivery.collection_method }}</strong></div>
          <div v-if="orderDetail.delivery?.status"><span>Status kirim</span><strong>{{ orderDetail.delivery.status }}</strong></div>
        </div>

        <div v-if="orderDetail.delivery?.tracking_history && orderDetail.delivery.tracking_history.length" class="order-track-list">
          <div v-for="(h, i) in orderDetail.delivery.tracking_history" :key="i" class="order-track">
            <strong>{{ h.status }}</strong>
            <span class="muted">{{ h.updated_at }}</span>
            <span>{{ h.note }}</span>
          </div>
        </div>

        <div class="modal-actions">
          <button v-if="!orderDetail.courier_company && orderDetail.status !== 'completed' && orderDetail.status !== 'cancelled' && orderDetail.status !== 'expired'" class="primary" type="button" :disabled="savingOrder" @click="completeOrder">Selesai / Siap Jemput</button>
          <button v-if="orderDetail.delivery?.biteship_order_id" class="ghost--sm" type="button" :disabled="savingOrder" @click="refreshTracking(orderDetail)">Tarik Tracking</button>
          <button class="ghost--sm" type="button" @click="closeOrder">Tutup</button>
        </div>
      </template>
    </div>
  </div>

  <!-- Modal proses kirim (terpisah dari detail) -->
  <div v-if="shipModal && shipOrder" class="modal-overlay" role="dialog" aria-modal="true" aria-label="Proses kirim" @click.self="closeShip">
    <div class="modal-card">
      <div class="modal-head">
        <h3>Proses Kirim · {{ shipOrder.order_number }} <span class="status" :class="`status--${shipOrder.status}`">{{ shipOrder.status }}</span></h3>
        <button class="modal-x" type="button" aria-label="Tutup" @click="closeShip">×</button>
      </div>
      <div class="detail-grid">
        <div><span>Pelanggan</span><strong>{{ shipOrder.customer_name }}</strong></div>
        <div><span>Kurir</span><strong>{{ shipOrder.courier_company ? `${shipOrder.courier_company} ${shipOrder.courier_type || ''}` : 'Jemput ke Toko' }}</strong></div>
        <div><span>Penerima</span><strong>{{ shipOrder.recipient?.recipientName }}</strong><small>{{ shipOrder.recipient?.recipientPhone }}</small></div>
        <div><span>Alamat</span><strong>{{ shipOrder.recipient?.addressLine }}</strong><small>{{ [shipOrder.recipient?.areaName, shipOrder.recipient?.postalCode].filter(Boolean).join(' · ') }}</small><small v-if="shipOrder.recipient?.notes">Catatan: {{ shipOrder.recipient.notes }}</small></div>
      </div>
      <p class="ship-warning">Setelah diproses, status otomatis menjadi <strong>Dikirim</strong> dan tidak dapat dikembalikan. Kurir &amp; resi dibuat otomatis oleh Biteship. Pastikan pesanan sudah siap dikirim.</p>
      <label class="field"><span>Ketik <b>KIRIM</b> untuk mengaktifkan tombol</span>
        <input v-model="shipConfirm" autocomplete="off" placeholder="KIRIM">
      </label>
      <div class="ship-actions">
        <template v-if="shipMethods.length">
          <button v-for="m in shipMethods" :key="m" class="primary" type="button" :disabled="shipBusy || shipConfirm.trim() !== 'KIRIM'" @click="doShip(m)">{{ shipBusy ? 'Memproses…' : `Kirim · ${m}` }}</button>
        </template>
        <template v-else>
          <button class="ghost--sm" type="button" :disabled="shipBusy" @click="loadShipMethods">{{ shipBusy ? 'Memuat…' : 'Muat metode' }}</button>
          <button class="primary" type="button" :disabled="shipBusy || shipConfirm.trim() !== 'KIRIM'" @click="doShip('')">{{ shipBusy ? 'Memproses…' : 'Proses Kirim' }}</button>
        </template>
      </div>
    </div>
  </div>

  <!-- Modal tab deskripsi -->
  <div v-if="tabsModal" class="modal-overlay modal-overlay--top" role="dialog" aria-modal="true" aria-label="Kelola tab deskripsi" @click.self="closeTabs">
    <div class="modal-card modal-card--wide">
      <div class="modal-head">
        <h3>Tab Deskripsi — {{ tabsProduct.name }}</h3>
        <button class="modal-x" type="button" aria-label="Tutup" @click="closeTabs">×</button>
      </div>
      <div class="variant-toolbar">
        <button class="primary primary--sm" type="button" @click="openTab(null)">+ Tab</button>
      </div>
      <div v-if="tabsLoading" class="skeleton-list" aria-hidden="true"><span v-for="n in 3" :key="n" class="skeleton skeleton--row"></span></div>
      <p v-else-if="!tabsList.length" class="muted">Belum ada tab. Tambahkan tab deskripsi.</p>
      <div v-else class="table-wrap">
        <table class="orders-table">
          <thead><tr><th>Judul</th><th>Urutan</th><th></th></tr></thead>
          <tbody>
            <tr v-for="t in tabsList" :key="t.id">
              <td data-label="Judul">{{ t.title }}</td>
              <td data-label="Urutan">{{ t.sort_order }}</td>
              <td data-label="Aksi"><span class="row-actions"><button class="link" type="button" @click="openTab(t)">Edit</button><button class="link-danger" type="button" @click="removeTab(t)">Hapus</button></span></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Modal form tab deskripsi -->
  <div v-if="tabModal" class="modal-overlay modal-overlay--top2" role="dialog" aria-modal="true" aria-label="Form tab deskripsi" @click.self="tabModal = false">
    <form class="modal-card modal-card--xl" @submit.prevent="saveTab">
      <div class="modal-head">
        <h3>{{ tabForm.id ? 'Edit tab' : 'Tambah tab' }}</h3>
        <button class="modal-x" type="button" aria-label="Tutup" @click="tabModal = false">×</button>
      </div>
      <div class="field-row">
        <label class="field"><span>Judul tab</span><input v-model="tabForm.title" required placeholder="Contoh: Ukuran Pas Foto"></label>
        <label class="field"><span>Urutan</span><input v-model.number="tabForm.sort_order" type="number" min="0"></label>
      </div>
      <label class="field"><span>Isi tab</span>
        <RichTextEditor v-model="tabForm.content_html" :min-height="300" placeholder="Tulis isi tab di sini..." />
      </label>
      <div class="modal-actions">
        <button class="ghost--sm" type="button" @click="tabModal = false">Batal</button>
        <button class="primary" type="submit" :disabled="tabSaving">{{ tabSaving ? 'Menyimpan…' : 'Simpan' }}</button>
      </div>
    </form>
  </div>

  <!-- Modal detail event user -->
  <div v-if="eventUserModal" class="modal-overlay" role="dialog" aria-modal="true" aria-label="Detail event user" @click.self="closeEventUser">
    <div class="modal-card modal-card--wide">
      <div class="modal-head">
        <h3 v-if="eventUserDetail">{{ eventUserDetail.full_name }} <span class="status" :class="eventUserDetail.status === 'active' ? 'status--paid' : 'status--expired'">{{ eventUserDetail.status }}</span></h3>
        <h3 v-else>Detail event user</h3>
        <button class="modal-x" type="button" aria-label="Tutup" @click="closeEventUser">×</button>
      </div>
      <div v-if="eventUserLoading" class="skeleton-list" aria-hidden="true"><span v-for="n in 3" :key="n" class="skeleton skeleton--row"></span></div>
      <template v-else-if="eventUserDetail">
        <div class="detail-grid">
          <div><span>Email</span><strong>{{ eventUserDetail.email }}</strong></div>
          <div><span>Saldo</span><strong>{{ rupiah.format(eventUserDetail.balance) }}</strong></div>
        </div>

        <h4 class="detail-sub">Limit</h4>
        <div class="field-row">
          <label class="field"><span>Maks event (kosong = default {{ eventUsersDefaults.maxEvents }})</span><input v-model="eventUserLimits.max_events" type="number" min="0" placeholder="default"></label>
          <label class="field"><span>Maks foto/event (kosong = default {{ eventUsersDefaults.maxPhotos }})</span><input v-model="eventUserLimits.max_photos_per_event" type="number" min="0" placeholder="default"></label>
        </div>
        <div class="modal-actions">
          <button class="ghost--sm" type="button" :disabled="eventUserSaving" @click="saveEventUserLimits">Simpan limit</button>
          <button class="ghost--sm" type="button" :disabled="eventUserSaving" @click="setEventUserApproval(eventUserDetail, !eventUserDetail.approved)">{{ eventUserDetail.approved ? 'Batalkan persetujuan' : 'Setujui user' }}</button>
          <button class="link-danger" type="button" :disabled="eventUserSaving" @click="toggleEventUserStatus">{{ eventUserDetail.status === 'blocked' ? 'Aktifkan kembali' : 'Blokir user' }}</button>
        </div>

        <h4 class="detail-sub">Event</h4>
        <div class="table-wrap">
          <table class="orders-table variant-table">
            <thead><tr><th>Nama</th><th>Tanggal</th><th>Foto</th><th>Status</th></tr></thead>
            <tbody>
              <tr v-for="ev in eventUserDetail.events" :key="ev.id">
                <td data-label="Nama">{{ ev.name }}</td>
                <td data-label="Tanggal">{{ ev.event_date }}</td>
                <td data-label="Foto">{{ ev.photo_count }}</td>
                <td data-label="Status"><span class="status" :class="ev.status === 'published' ? 'status--paid' : 'status--unpaid'">{{ ev.status }}</span></td>
              </tr>
              <tr v-if="!eventUserDetail.events.length"><td colspan="4" class="muted">Belum ada event.</td></tr>
            </tbody>
          </table>
        </div>

        <h4 class="detail-sub">Riwayat Saldo</h4>
        <div class="table-wrap">
          <table class="orders-table variant-table">
            <thead><tr><th>Tanggal</th><th>Tipe</th><th>Order</th><th>Jumlah</th></tr></thead>
            <tbody>
              <tr v-for="l in eventUserDetail.ledger" :key="l.id">
                <td data-label="Tanggal">{{ formatDateTime(l.created_at) }}</td>
                <td data-label="Tipe">{{ l.type }}</td>
                <td data-label="Order">{{ l.order_id || '—' }}</td>
                <td data-label="Jumlah">{{ rupiah.format(l.amount) }}</td>
              </tr>
              <tr v-if="!eventUserDetail.ledger.length"><td colspan="4" class="muted">Belum ada transaksi.</td></tr>
            </tbody>
          </table>
        </div>
      </template>
    </div>
  </div>

  <!-- Modal detail pelanggan -->
  <div v-if="custModal" class="modal-overlay" role="dialog" aria-modal="true" aria-label="Detail pelanggan" @click.self="closeCustomer">
    <div class="modal-card modal-card--wide">
      <div class="modal-head">
        <h3 v-if="custDetail">{{ custDetail.full_name }} <span class="status" :class="custDetail.status === 'active' ? 'status--paid' : 'status--expired'">{{ custDetail.status }}</span></h3>
        <h3 v-else>Detail pelanggan</h3>
        <button class="modal-x" type="button" aria-label="Tutup" @click="closeCustomer">×</button>
      </div>
      <div v-if="custLoadingDetail" class="skeleton-list" aria-hidden="true"><span v-for="n in 3" :key="n" class="skeleton skeleton--row"></span></div>
      <template v-else-if="custDetail">
        <div class="detail-grid">
          <div><span>Email</span><strong>{{ custDetail.email }}</strong></div>
          <div><span>Telepon</span><strong>{{ custDetail.phone || '—' }}</strong></div>
          <div><span>Terdaftar</span><strong>{{ formatDateTime(custDetail.created_at) }}</strong></div>
          <div><span>Login terakhir</span><strong>{{ formatDateTime(custDetail.last_login_at) }}</strong></div>
        </div>

        <h4 class="detail-sub">Alamat</h4>
        <p v-if="!custDetail.addresses.length" class="muted">Belum ada alamat.</p>
        <div v-for="a in custDetail.addresses" :key="a.id" class="detail-recipient">
          <strong>{{ a.label }}<span v-if="a.is_default"> · Utama</span></strong>
          <span>{{ a.recipient_name }} · {{ a.recipient_phone }}</span>
          <span>{{ a.address_line }}</span>
          <span>{{ [a.village_name, a.district_name, a.regency_name, a.province_name, a.postal_code].filter(Boolean).join(', ') }}</span>
        </div>

        <h4 class="detail-sub">Pesanan</h4>
        <p v-if="!custDetail.orders.length" class="muted">Belum ada pesanan.</p>
        <div v-else class="table-wrap">
          <table class="orders-table variant-table">
            <thead><tr><th>No.</th><th>Tanggal</th><th>Total</th><th>Status</th></tr></thead>
            <tbody>
              <tr v-for="o in custDetail.orders" :key="o.id">
                <td data-label="No.">{{ o.order_number }}</td>
                <td data-label="Tanggal">{{ formatDate(o.created_at) }}</td>
                <td data-label="Total">{{ rupiah.format(o.total) }}</td>
                <td data-label="Status"><span class="status" :class="`status--${o.status}`">{{ o.status }}</span></td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="modal-actions">
          <button class="ghost--sm" type="button" @click="closeCustomer">Tutup</button>
          <button class="link-danger" type="button" :disabled="custSaving" @click="toggleCustomerStatus">{{ custDetail.status === 'blocked' ? 'Aktifkan kembali' : 'Blokir pelanggan' }}</button>
        </div>
      </template>
    </div>
  </div>

  <div v-if="pwModal" class="modal-overlay" role="dialog" aria-modal="true" aria-label="Ganti password" @click.self="closeChangePassword">
    <form class="modal-card" @submit.prevent="submitChangePassword">
      <div class="modal-head"><h3>Ganti password</h3><button class="modal-x" type="button" aria-label="Tutup" @click="closeChangePassword">×</button></div>
      <label class="field"><span>Password saat ini</span><input v-model="pwForm.current_password" type="password" required autocomplete="current-password"></label>
      <label class="field"><span>Password baru</span><input v-model="pwForm.new_password" type="password" required minlength="8" autocomplete="new-password"></label>
      <label class="field"><span>Konfirmasi password baru</span><input v-model="pwForm.confirm_password" type="password" required autocomplete="new-password"></label>
      <p v-if="pwError" class="error">{{ pwError }}</p>
      <div class="modal-actions"><button class="ghost--sm" type="button" @click="closeChangePassword">Batal</button><button class="primary" type="submit" :disabled="pwBusy">{{ pwBusy ? 'Menyimpan…' : 'Simpan' }}</button></div>
    </form>
  </div>

    <MediaPicker v-if="pickerOpen" :mode="pickerMode" @select="onMediaPick" @select-many="onMediaPickMany" @close="pickerOpen = false" />
</template>
