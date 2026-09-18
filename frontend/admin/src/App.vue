<script setup>
import { computed, onMounted, ref } from 'vue'

const user = ref(null)
const error = ref('')
const notice = ref('')
const form = ref({ email: '', password: '' })
const navOpen = ref(false)
const busy = ref(false)
const view = ref('orders')
const rupiah = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 })
const STOREFRONT = 'https://vpictura.com'
function mediaUrl(url) { if (!url) return ''; return /^https?:/i.test(url) ? url : `${STOREFRONT}${url}` }
const navItems = [
  { key: 'orders', label: 'Pesanan' },
  { key: 'categories', label: 'Kategori' },
  { key: 'products', label: 'Produk' },
  { key: 'customers', label: 'Pelanggan' },
]
const titles = { orders: 'Pesanan', categories: 'Kategori', products: 'Produk', customers: 'Pelanggan' }
const currentTitle = computed(() => titles[view.value] || 'Dashboard')

async function api(path, body) {
  const r = await fetch(`/api/Admin/${path}`, { method: body ? 'POST' : 'GET', credentials: 'include', headers: body ? { 'Content-Type': 'application/json' } : {}, body: body ? JSON.stringify(body) : undefined })
  const p = await r.json()
  if (!r.ok || !p.status) throw new Error(p.message)
  return p.data
}
let flashTimer
function flash(message) { notice.value = message; window.clearTimeout(flashTimer); flashTimer = window.setTimeout(() => { notice.value = '' }, 3500) }

// ---- Auth ----
async function login() {
  busy.value = true; error.value = ''
  try { user.value = await api('Auth/login', form.value); form.value = { email: '', password: '' }; await loadView('orders'); flash('Berhasil masuk.') }
  catch (e) { error.value = e.message }
  finally { busy.value = false }
}
async function logout() { try { await api('Auth/logout', {}) } catch {} user.value = null; navOpen.value = false }
function switchView(key) { view.value = key; navOpen.value = false; loadView(key) }
function loadView(key) { if (key === 'orders') return loadOrders(); if (key === 'categories') return loadCategories(); if (key === 'products') return loadProducts(); if (key === 'customers') return loadCustomers() }

// ---- Orders ----
const orders = ref([])
const search = ref('')
const statusFilter = ref('')
const savingId = ref(null)
const loading = ref(false)
async function loadOrders() { loading.value = true; try { orders.value = (await api('Orders/index')).items || [] } catch (e) { error.value = e.message } finally { loading.value = false } }
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
function emptyProduct() { return { id: null, name: '', slug: '', category_id: '', short_description: '', description: '', base_price: 0, weight_grams: 0, length_mm: '', width_mm: '', height_mm: '', cover_image_url: '', is_featured: false, popularity: 0, status: 'published' } }
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
function mapProduct(p) { return { id: p.id, name: p.name, slug: p.slug, category_id: p.category_id ?? '', short_description: p.short_description || '', description: p.description || '', base_price: Number(p.base_price) || 0, weight_grams: Number(p.weight_grams) || 0, length_mm: p.length_mm ?? '', width_mm: p.width_mm ?? '', height_mm: p.height_mm ?? '', cover_image_url: p.cover_image_url || '', is_featured: !!p.is_featured, popularity: Number(p.popularity) || 0, status: p.status } }
async function openProduct(row) {
  if (row && row.id) {
    try { prodForm.value = mapProduct(await api(`Products/show/${row.id}`)) } catch (e) { error.value = e.message; return }
    await loadMedia(prodForm.value.id)
  } else { prodForm.value = emptyProduct(); prodMedia.value = [] }
  prodModal.value = true
}
function closeProduct() { prodModal.value = false; prodMedia.value = []; variantModal.value = false; variants.value = [] }
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
const mediaUploading = ref(false)
async function loadMedia(pid) { try { prodMedia.value = (await api(`Products/media/${pid}`)).items || [] } catch (e) { prodMedia.value = [] } }
async function uploadMedia(event) {
  const file = event.target.files?.[0]
  if (!file || !prodForm.value.id) return
  mediaUploading.value = true
  try {
    const fd = new FormData()
    fd.append('file', file)
    const r = await fetch(`/api/Admin/Products/upload-media/${prodForm.value.id}`, { method: 'POST', credentials: 'include', body: fd })
    const p = await r.json()
    if (!r.ok || !p.status) throw new Error(p.message)
    await loadMedia(prodForm.value.id)
    flash('Media diunggah.')
  } catch (e) { error.value = e.message } finally { mediaUploading.value = false; event.target.value = '' }
}
async function removeMedia(item) { if (!window.confirm('Hapus media ini?')) return; try { await api(`Products/remove-media/${item.id}`, {}); await loadMedia(prodForm.value.id); flash('Media dihapus.') } catch (e) { error.value = e.message } }
async function moveMedia(item, dir) { const list = [...prodMedia.value]; const i = list.findIndex((m) => m.id === item.id); const j = i + dir; if (j < 0 || j >= list.length) return; [list[i], list[j]] = [list[j], list[i]]; prodMedia.value = list; try { await api(`Products/reorder-media/${prodForm.value.id}`, { order: list.map((m) => m.id) }) } catch (e) { error.value = e.message } }
async function setCover(item) { try { const d = await api(`Products/set-cover/${prodForm.value.id}`, { media_id: item.id }); prodForm.value.cover_image_url = d.coverImage; flash('Cover diperbarui.') } catch (e) { error.value = e.message } }

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

onMounted(async () => { try { user.value = await api('Auth/me'); await loadView('orders') } catch {} })
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
        <span class="avatar" aria-hidden="true">{{ user.name.charAt(0).toUpperCase() }}</span>
        <div>
          <strong>{{ user.name }}</strong>
          <small>{{ user.role }}</small>
        </div>
        <button class="link" type="button" @click="logout">Keluar</button>
      </div>
    </aside>
    <div v-if="navOpen" class="scrim" @click="navOpen = false"></div>

    <div id="main" tabindex="-1" class="admin-main">
      <header class="topbar">
        <button class="menu-btn" type="button" aria-label="Buka menu" @click="navOpen = !navOpen">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
        </button>
        <h2>{{ currentTitle }}</h2>
        <button v-if="view === 'orders'" class="ghost--sm" type="button" :disabled="loading" @click="loadOrders">Muat ulang</button>
        <button v-else-if="view === 'categories'" class="primary primary--sm" type="button" @click="openCategory(null)">+ Tambah kategori</button>
        <button v-else-if="view === 'products'" class="primary primary--sm" type="button" @click="openProduct(null)">+ Tambah produk</button>
        <button v-else-if="view === 'customers'" class="ghost--sm" type="button" :disabled="custLoading" @click="loadCustomers">Muat ulang</button>
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

      <!-- Pesanan -->
      <template v-if="view === 'orders'">
        <section class="stats">
          <article class="stat"><span>Total</span><strong>{{ orders.length }}</strong></article>
          <article class="stat"><span>Perlu diproses</span><strong>{{ countBy('processing') }}</strong></article>
          <article class="stat"><span>Dikirim</span><strong>{{ countBy('shipped') }}</strong></article>
          <article class="stat"><span>Selesai</span><strong>{{ countBy('completed') }}</strong></article>
        </section>

        <section class="panel" :aria-busy="loading">
          <div class="panel__head">
            <input v-model="search" class="search" type="search" placeholder="Cari nomor pesanan atau nama…">
            <select v-model="statusFilter">
              <option value="">Semua status</option>
              <option value="processing">Processing</option>
              <option value="shipped">Shipped</option>
              <option value="completed">Completed</option>
              <option value="cancelled">Cancelled</option>
            </select>
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
                    <select :value="order.status" :disabled="savingId === order.id" @change="update(order, $event.target.value)">
                      <option value="processing">Processing</option>
                      <option value="shipped">Shipped</option>
                      <option value="completed">Completed</option>
                      <option value="cancelled">Cancelled</option>
                    </select>
                  </td>
                  <td data-label="Aksi"><button class="link" type="button" @click="openOrder(order)">Detail</button></td>
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
    <form class="modal-card" @submit.prevent="saveProduct">
      <div class="modal-head">
        <h3>{{ prodForm.id ? 'Edit produk' : 'Tambah produk' }}</h3>
        <button class="modal-x" type="button" aria-label="Tutup" @click="closeProduct">×</button>
      </div>
      <label class="field"><span>Nama</span><input v-model="prodForm.name" required placeholder="Contoh: Cetak Foto" @input="onProductName"></label>
      <label class="field"><span>Slug (opsional)</span><input v-model="prodForm.slug" placeholder="otomatis dari nama"></label>
      <label class="field"><span>Kategori</span>
        <select v-model="prodForm.category_id">
          <option value="">— Tanpa kategori —</option>
          <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
      </label>
      <label class="field"><span>Harga dasar (Rp)</span><input v-model.number="prodForm.base_price" type="number" min="0"></label>
      <div class="field-row">
        <label class="field"><span>Berat (gram)</span><input v-model.number="prodForm.weight_grams" type="number" min="0"></label>
        <label class="field"><span>Populer</span><input v-model.number="prodForm.popularity" type="number" min="0"></label>
      </div>
      <div class="field-row field-row--3">
        <label class="field"><span>Panjang (mm)</span><input v-model="prodForm.length_mm" type="number" min="0"></label>
        <label class="field"><span>Lebar (mm)</span><input v-model="prodForm.width_mm" type="number" min="0"></label>
        <label class="field"><span>Tinggi (mm)</span><input v-model="prodForm.height_mm" type="number" min="0"></label>
      </div>
      <label class="field"><span>Cover image URL</span><input v-model="prodForm.cover_image_url" placeholder="/uploads/... atau https://..."></label>
      <div v-if="prodForm.id" class="field">
        <span>Galeri media</span>
        <div class="media-grid">
          <div v-for="m in prodMedia" :key="m.id" class="media-item" :class="{ 'is-cover': m.url === prodForm.cover_image_url }">
            <img :src="mediaUrl(m.url)" alt="">
            <div class="media-tools">
              <button type="button" class="media-btn" :disabled="m.url === prodForm.cover_image_url" title="Jadikan cover" @click="setCover(m)">★</button>
              <button type="button" class="media-btn" title="Naik" @click="moveMedia(m, -1)">↑</button>
              <button type="button" class="media-btn" title="Turun" @click="moveMedia(m, 1)">↓</button>
              <button type="button" class="media-btn media-btn--danger" title="Hapus" @click="removeMedia(m)">×</button>
            </div>
          </div>
          <label class="media-add" title="Unggah media">
            <input type="file" accept="image/jpeg,image/png,image/webp" :disabled="mediaUploading" @change="uploadMedia">
            <span>{{ mediaUploading ? '…' : '+' }}</span>
          </label>
        </div>
        <small class="muted">{{ prodMedia.length }} media · JPG/PNG/WEBP · tanda ★ = cover</small>
      </div>
      <div v-if="prodForm.id" class="field">
        <span>Varian</span>
        <button class="ghost--sm variant-open" type="button" @click="openVariants">Kelola varian (grup &amp; nilai)</button>
      </div>
      <label class="field"><span>Deskripsi singkat</span><input v-model="prodForm.short_description"></label>
      <label class="field"><span>Deskripsi</span><textarea v-model="prodForm.description" rows="3"></textarea></label>
      <div class="field-row">
        <label class="field"><span>Status</span>
          <select v-model="prodForm.status">
            <option value="published">Published</option>
            <option value="draft">Draft</option>
            <option value="archived">Archived</option>
          </select>
        </label>
        <label class="check check--end"><input v-model="prodForm.is_featured" type="checkbox"> Unggulan</label>
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
      <div class="field-row">
        <label class="field"><span>Harga tambahan (Rp)</span><input v-model.number="valueForm.price_delta" type="number"></label>
        <label class="field"><span>Image suffix</span><input v-model="valueForm.image_suffix" placeholder="mis: full_logo"></label>
      </div>
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
          <div><span>Total</span><strong>{{ rupiah.format(orderDetail.total) }}</strong></div>
        </div>

        <h4 class="detail-sub">Item</h4>
        <div class="table-wrap">
          <table class="orders-table variant-table">
            <thead><tr><th>Produk</th><th>Pilihan</th><th>Qty</th><th>Harga</th><th>Total</th></tr></thead>
            <tbody>
              <tr v-for="it in orderDetail.items" :key="it.id">
                <td data-label="Produk">{{ it.product_name }}</td>
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
        </div>

        <h4 class="detail-sub">Pembayaran</h4>
        <div class="detail-grid">
          <div><span>Status</span><strong><span class="status" :class="`status--${orderDetail.payment?.status || 'unpaid'}`">{{ orderDetail.payment?.status || 'unpaid' }}</span></strong></div>
          <div><span>Metode</span><strong>{{ orderDetail.payment?.payment_type || '—' }}</strong></div>
          <div><span>Transaksi</span><strong>{{ orderDetail.payment?.transaction_id || '—' }}</strong></div>
          <div><span>Dibayar</span><strong>{{ formatDateTime(orderDetail.payment?.paid_at) }}</strong></div>
        </div>
        <div class="detail-actions-line">
          <button v-if="orderDetail.payment?.status !== 'paid'" class="ghost--sm" type="button" :disabled="savingOrder" @click="markPaid">Tandai lunas</button>
        </div>

        <h4 class="detail-sub">Pengiriman</h4>
        <div class="field-row">
          <label class="field"><span>Kurir</span><input v-model="deliveryForm.courier_company" placeholder="jne / jnt / sicepat"></label>
          <label class="field"><span>Layanan</span><input v-model="deliveryForm.courier_service" placeholder="REG"></label>
          <label class="field"><span>No. Resi</span><input v-model="deliveryForm.tracking_number"></label>
          <label class="field"><span>Status kirim</span>
            <select v-model="deliveryForm.status"><option value="pending">Pending</option><option value="shipped">Shipped</option><option value="delivered">Delivered</option></select>
          </label>
        </div>
        <div class="modal-actions">
          <button class="ghost--sm" type="button" @click="closeOrder">Tutup</button>
          <button class="primary" type="button" :disabled="savingOrder" @click="saveDelivery">Simpan pengiriman</button>
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
</template>
