<script setup>
import { computed, nextTick, onMounted, ref } from 'vue'

const catalog = ref({ categories: [], featured: [] })
const allProducts = ref([])
const activeCategory = ref(null)
const product = ref(null)
const loading = ref(true)
const error = ref('')
const customer = ref(null)
const googleClientId = ref('')
const addressBook = ref(false)
const addresses = ref([])
const addressForm = ref({ label: '', recipient_name: '', recipient_phone: '', address_line: '', is_default: false })
const selectedOptions = ref([])
const activeImage = ref(null)
const quote = ref(null)
const uploadedFile = ref(null)
const cart = ref({ items: [], total: 0 })
const cartOpen = ref(false)
const ordersOpen = ref(false)
const orders = ref([])
const notice = ref('')
const addingToCart = ref(false)
const signingIn = ref(false)
const loginOpen = ref(false)
const googleReady = ref(false)
const googleLoading = ref(false)
const googleError = ref('')
const googleBtn = ref(null)
let googleButtonRendered = false
const rupiah = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 })
const cartCount = computed(() => cart.value.items.reduce((n, item) => n + (Number(item.quantity) || 0), 0))
const activeCategoryName = computed(() => activeCategory.value ? (catalog.value.categories.find((c) => c.slug === activeCategory.value)?.name || 'Katalog') : 'Pilihan populer')
const displayProducts = computed(() => activeCategory.value ? allProducts.value.filter((p) => p.category?.slug === activeCategory.value) : catalog.value.featured)
const gallery = computed(() => {
  if (!product.value) return []
  const items = []
  if (product.value.coverImage) items.push({ url: product.value.coverImage, alt: product.value.name })
  for (const media of product.value.media || []) {
    if (media.url && !items.some((item) => item.url === media.url)) items.push({ url: media.url, alt: media.alt_text || product.value.name })
  }
  return items
})
const heroImage = computed(() => activeImage.value || gallery.value[0]?.url || null)

const API_BASE = (import.meta.env.VITE_API_BASE_URL || '').replace(/\/+$/, '')
function apiUrl(path) { return API_BASE ? `${API_BASE}${path}` : `/api${path}` }
async function request(path) {
  const url = apiUrl(path.startsWith('/') ? path : `/Store/Catalog/${path}`)
  const response = await fetch(url, { credentials: 'include', headers: { Accept: 'application/json' } })
  const payload = await response.json()
  if (!response.ok || !payload.status) throw new Error(payload.message || 'Tidak dapat memuat katalog.')
  return payload.data
}
async function post(path, body) {
  const response = await fetch(apiUrl(path), { method: 'POST', credentials: 'include', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify(body || {}) })
  const payload = await response.json()
  if (!response.ok || !payload.status) throw new Error(payload.message || 'Terjadi kesalahan.')
  return payload.data
}
let flashTimer
function flash(message) { notice.value = message; window.clearTimeout(flashTimer); flashTimer = window.setTimeout(() => { notice.value = '' }, 3500) }
async function loadHome() { try { loading.value = true; catalog.value = await request('home'); allProducts.value = (await request('products?limit=48')).items } catch (e) { error.value = e.message } finally { loading.value = false } }
function waitForGoogle(timeout = 8000) {
  return new Promise((resolve) => {
    const started = Date.now()
    const poll = () => {
      if (window.google?.accounts?.id) return resolve(true)
      if (Date.now() - started > timeout) return resolve(false)
      window.setTimeout(poll, 150)
    }
    poll()
  })
}
function captureGsiErrors() {
  if (window.__vpGsiHooked) return
  window.__vpGsiHooked = true
  const original = console.error
  console.error = (...args) => {
    const text = args.map((a) => (a && a.message) ? a.message : String(a)).join(' ')
    if (text.includes('GSI_LOGGER')) googleError.value = text
    original.apply(console, args)
  }
}
async function ensureGoogleReady() {
  if (googleReady.value) return true
  if (!googleClientId.value) return false
  captureGsiErrors()
  googleLoading.value = true
  googleError.value = ''
  const ready = await waitForGoogle()
  googleLoading.value = false
  if (!ready) { googleError.value = 'Skrip Google gagal dimuat. Matikan adblock lalu muat ulang halaman.'; return false }
  window.google.accounts.id.initialize({ client_id: googleClientId.value, callback: googleLogin })
  googleReady.value = true
  return true
}
function renderGoogleButton() {
  if (googleButtonRendered || !googleReady.value || !googleBtn.value) return
  window.google.accounts.id.renderButton(googleBtn.value, { type: 'standard', theme: 'outline', size: 'large', shape: 'pill', text: 'signin_with', locale: 'id', width: 280 })
  googleButtonRendered = true
}
async function loadAuth() { try { const data=await request('/Customer/Auth/config'); googleClientId.value=data.googleClientId } catch(e){ googleError.value = e.message } }
async function googleLogin(response) { signingIn.value = true; try { customer.value = await post('/Customer/Auth/google', { credential: response.credential }); loginOpen.value = false; await refreshCart(); flash('Berhasil masuk.') } catch(e){error.value=e.message} finally { signingIn.value = false } }
async function startGoogleLogin() { if(!googleClientId.value){error.value='Login Google belum dikonfigurasi.';return} loginOpen.value = true; if(await ensureGoogleReady()){ await nextTick(); renderGoogleButton() } }
async function openAddresses() { if(!customer.value){startGoogleLogin();return} try { const data=await request('/Customer/Addresses/index');addresses.value=data.items;addressBook.value=true } catch(e){error.value=e.message} }
async function saveAddress() { try { await post('/Customer/Addresses/save', addressForm.value); addressForm.value={label:'',recipient_name:'',recipient_phone:'',address_line:'',is_default:false}; await openAddresses(); flash('Alamat disimpan.') } catch(e){error.value=e.message} }
async function setDefaultAddress(item) { try { await post(`/Customer/Addresses/set-default/${item.id}`); await openAddresses() } catch(e){ error.value=e.message } }
async function removeAddress(item) { try { await post(`/Customer/Addresses/remove/${item.id}`); await openAddresses() } catch(e){ error.value=e.message } }
function formatDate(value) { if (!value) return ''; const date = new Date(String(value).replace(' ', 'T')); return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) }
function chooseOption(group, value) { const inGroup = selectedOptions.value.filter((id) => group.values.some((item) => item.id === id)); if (inGroup.includes(value.id) && Number(group.is_required) !== 1) { selectedOptions.value = selectedOptions.value.filter((id) => id !== value.id) } else { selectedOptions.value = [...selectedOptions.value.filter(id => !group.values.some(item => item.id === id)), value.id] } quote.value = null }
async function getQuote() { try { const r=await fetch(apiUrl('/Customer/Configurator/quote'),{method:'POST',credentials:'include',headers:{'Content-Type':'application/json',Accept:'application/json'},body:JSON.stringify({product_id:product.value.id,option_value_ids:selectedOptions.value,quantity:1})});const p=await r.json();if(!r.ok||!p.status)throw new Error(p.message);quote.value=p.data } catch(e){error.value=e.message} }
async function uploadDesign(event) { const file=event.target.files?.[0];if(!file)return;if(!customer.value){startGoogleLogin();return}try{const form=new FormData();form.append('file',file);form.append('product_id',product.value.id);const r=await fetch(apiUrl('/Customer/Configurator/upload'),{method:'POST',credentials:'include',body:form});const p=await r.json();if(!r.ok||!p.status)throw new Error(p.message);uploadedFile.value=p.data;flash('Desain diunggah.')}catch(e){error.value=e.message} }
async function loadCart() { cart.value = await request('/Customer/Cart/index') }
async function openCart() { if(!customer.value){startGoogleLogin();return}try{await loadCart();cartOpen.value=true}catch(e){error.value=e.message} }
async function updateCartQty(item, quantity) { try { await post(`/Customer/Cart/update/${item.id}`, { quantity }); await loadCart() } catch(e){ error.value=e.message } }
async function removeCartItem(item) { try { await post(`/Customer/Cart/remove/${item.id}`); await loadCart(); flash('Item dihapus dari keranjang.') } catch(e){ error.value=e.message } }
async function addCart() { if(!customer.value){startGoogleLogin();return} addingToCart.value=true; try { await post('/Customer/Cart/add', { product_id: product.value.id, option_value_ids: selectedOptions.value, quantity: 1, upload_ids: uploadedFile.value ? [uploadedFile.value.id] : [] }); await loadCart(); flash('Produk ditambahkan ke keranjang.') } catch(e){ error.value=e.message } finally { addingToCart.value=false } }
async function openOrders() { if(!customer.value){startGoogleLogin();return}try{const data=await request('/Customer/Orders/index');orders.value=data.items;ordersOpen.value=true}catch(e){error.value=e.message} }
async function openProduct(slug) { try { product.value = await request(`show/${slug}`); selectedOptions.value=[];quote.value=null;uploadedFile.value=null;activeImage.value=null; window.scrollTo({ top: 0, behavior: 'smooth' }) } catch (e) { error.value = e.message } }
function goHome() { product.value = null; cartOpen.value = false; ordersOpen.value = false; addressBook.value = false; window.scrollTo({ top: 0, behavior: 'smooth' }) }
function scrollToCatalog() { document.getElementById('katalog')?.scrollIntoView({ behavior: 'smooth', block: 'start' }) }
async function restoreSession() { try { customer.value = await request('/Customer/Auth/me') } catch {} }
async function refreshCart() { if (!customer.value) return; try { cart.value = await request('/Customer/Cart/index') } catch {} }
onMounted(async()=>{await loadHome();await loadAuth();await restoreSession();await refreshCart()})
</script>

<template>
  <main class="app-shell">
    <a class="skip-link" href="#main">Lewati ke konten</a>
    <header class="site-header">
      <button class="brand" type="button" @click="goHome">
        <span class="brand-mark" aria-hidden="true">VP</span>
        <span class="brand-name">Vita Pictura</span>
      </button>
      <nav class="site-nav" aria-label="Navigasi utama">
        <button class="nav-link" type="button" @click="goHome">Katalog</button>
        <button class="nav-link" type="button" @click="openOrders">Pesanan</button>
        <button class="nav-link" type="button" @click="openCart">Keranjang<span v-if="cartCount" class="badge">{{ cartCount }}</span></button>
        <button class="nav-link account" type="button" @click="customer ? openAddresses() : startGoogleLogin()">
          <img v-if="customer && customer.avatarUrl" class="avatar" :src="customer.avatarUrl" :alt="customer.name">
          <span v-else class="avatar avatar--fallback" aria-hidden="true">{{ (customer ? customer.name : 'M').charAt(0).toUpperCase() }}</span>
          <span class="account-name">{{ customer ? customer.name : 'Masuk' }}</span>
        </button>
      </nav>
      <button class="mobile-cart" type="button" aria-label="Keranjang" @click="openCart">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M2 3h2.2l2.4 12.2a2 2 0 0 0 2 1.6h8.9a2 2 0 0 0 2-1.6L21 7H5"/></svg>
        <span v-if="cartCount" class="badge">{{ cartCount }}</span>
      </button>
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
    <div id="main" tabindex="-1">
    <template v-if="ordersOpen">
      <button class="back" type="button" @click="ordersOpen = false">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
        Kembali
      </button>
      <p class="category">Akun</p>
      <h1>Pesananmu</h1>

      <div v-if="!orders.length" class="state">
        <p class="description">Belum ada pesanan.</p>
        <button class="cta" type="button" @click="goHome">Mulai belanja</button>
      </div>

      <div v-else class="orders">
        <article v-for="order in orders" :key="order.id" class="order-card">
          <div class="order-card__head">
            <strong>{{ order.order_number }}</strong>
            <span class="status" :class="`status--${order.status}`">{{ order.status }}</span>
          </div>
          <span class="order-card__meta">{{ formatDate(order.created_at) }}</span>
          <div class="order-card__row">
            <span class="status" :class="`status--${order.payment_status || 'unpaid'}`">{{ order.payment_status || 'belum dibayar' }}</span>
            <strong>{{ rupiah.format(order.total) }}</strong>
          </div>
          <span v-if="order.tracking_number" class="order-card__meta">Resi: {{ order.tracking_number }}</span>
        </article>
      </div>
    </template>
    <template v-else-if="cartOpen">
      <button class="back" type="button" @click="cartOpen = false">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
        Kembali
      </button>
      <p class="category">Keranjang</p>
      <h1>Siap dicetak</h1>

      <div v-if="!cart.items.length" class="state">
        <p class="description">Keranjangmu masih kosong.</p>
        <button class="cta" type="button" @click="goHome">Mulai belanja</button>
      </div>

      <div v-else class="cart">
        <article v-for="item in cart.items" :key="item.id" class="cart-item">
          <div class="cart-item__media">
            <img v-if="item.image" :src="item.image" :alt="item.name">
            <span v-else>{{ item.name.charAt(0) }}</span>
          </div>
          <div class="cart-item__body">
            <strong>{{ item.name }}</strong>
            <span v-for="choice in item.selections" :key="choice.valueId" class="cart-item__choice">{{ choice.group }}: {{ choice.value }}</span>
            <div class="cart-item__row">
              <div class="qty">
                <button type="button" aria-label="Kurangi jumlah" @click="updateCartQty(item, Math.max(1, item.quantity - 1))">−</button>
                <span>{{ item.quantity }}</span>
                <button type="button" aria-label="Tambah jumlah" @click="updateCartQty(item, item.quantity + 1)">+</button>
              </div>
              <strong>{{ rupiah.format(item.totalPrice) }}</strong>
            </div>
            <button class="link-danger" type="button" @click="removeCartItem(item)">Hapus</button>
          </div>
        </article>

        <div class="cart-total">
          <span>Total</span>
          <strong>{{ rupiah.format(cart.total) }}</strong>
        </div>
      </div>
    </template>
    <template v-else-if="addressBook">
      <button class="back" type="button" @click="addressBook = false">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
        Kembali
      </button>
      <p class="category">Akun</p>
      <h1>Alamat tersimpan</h1>

      <p v-if="!addresses.length" class="description">Belum ada alamat tersimpan.</p>
      <article v-for="item in addresses" :key="item.id" class="address">
        <div class="address__head">
          <strong>{{ item.label }}</strong>
          <small v-if="item.isDefault">Utama</small>
        </div>
        <span>{{ item.recipientName }} · {{ item.recipientPhone }}</span>
        <span>{{ item.addressLine }}</span>
        <div class="address__actions">
          <button v-if="!item.isDefault" class="link" type="button" @click="setDefaultAddress(item)">Jadikan utama</button>
          <button class="link-danger" type="button" @click="removeAddress(item)">Hapus</button>
        </div>
      </article>

      <form class="address-form" @submit.prevent="saveAddress">
        <h2>Tambah lokasi</h2>
        <label class="field"><span>Label</span><input v-model="addressForm.label" required placeholder="Contoh: Rumah"></label>
        <label class="field"><span>Nama penerima</span><input v-model="addressForm.recipient_name" required placeholder="Nama lengkap"></label>
        <label class="field"><span>Nomor WhatsApp</span><input v-model="addressForm.recipient_phone" required placeholder="08xxxxxxxxxx"></label>
        <label class="field"><span>Alamat lengkap</span><textarea v-model="addressForm.address_line" required placeholder="Jalan, nomor, kelurahan, kota, kode pos"></textarea></label>
        <label class="check"><input v-model="addressForm.is_default" type="checkbox"> Jadikan alamat utama</label>
        <button class="cta" type="submit">Simpan alamat</button>
      </form>
    </template>
    <template v-else-if="product">
      <button class="back" type="button" @click="product = null">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
        Kembali ke katalog
      </button>

      <div class="product-detail">
        <div class="product-gallery">
          <div class="product-gallery__main">
            <img v-if="heroImage" :src="heroImage" :alt="product.name">
            <span v-else class="product-gallery__fallback">Preview produk</span>
          </div>
          <div v-if="gallery.length > 1" class="product-gallery__thumbs">
            <button v-for="(media, index) in gallery" :key="index" class="thumb" type="button" :class="{ 'is-active': heroImage === media.url }" @click="activeImage = media.url">
              <img :src="media.url" :alt="media.alt">
            </button>
          </div>
        </div>

        <div class="product-info">
          <p class="category">{{ product.category.name }}</p>
          <h1>{{ product.name }}</h1>
          <p class="price">Mulai dari {{ rupiah.format(product.price) }}</p>
          <p class="description">{{ product.description }}</p>

          <section v-for="group in product.options" :key="group.id" class="options">
            <div class="options__head">
              <strong>{{ group.name }}</strong>
              <span v-if="Number(group.is_required) === 1" class="options__req">Wajib</span>
              <span v-else class="options__opt">Opsional</span>
            </div>
            <button v-for="value in group.values" :key="value.id" type="button" :class="{ selected: selectedOptions.includes(value.id) }" @click="chooseOption(group, value)">
              <span>{{ value.name }}</span>
              <small v-if="value.price_delta">+{{ rupiah.format(value.price_delta) }}</small>
            </button>
          </section>

          <section class="upload">
            <strong>Unggah desain atau foto</strong>
            <input accept="image/jpeg,image/png,application/pdf,application/zip" type="file" @change="uploadDesign">
            <small>JPG, PNG, PDF, atau ZIP · maksimal 50 MB</small>
            <span v-if="uploadedFile">{{ uploadedFile.name }} siap digunakan</span>
          </section>

          <div class="product-summary">
            <div>
              <span class="product-summary__label">{{ quote ? 'Total estimasi' : 'Mulai dari' }}</span>
              <strong class="price">{{ rupiah.format(quote ? quote.totalPrice : product.price) }}</strong>
            </div>
            <button class="ghost ghost--sm" type="button" @click="getQuote">Hitung harga</button>
          </div>

          <button class="cta product-add" type="button" :disabled="addingToCart" @click="addCart">{{ addingToCart ? 'Menambahkan…' : 'Tambah ke keranjang' }}</button>
        </div>
      </div>

      <div class="product-cta-bar">
        <div>
          <span class="product-summary__label">{{ quote ? 'Total estimasi' : 'Mulai dari' }}</span>
          <strong>{{ rupiah.format(quote ? quote.totalPrice : product.price) }}</strong>
        </div>
        <button class="cta" type="button" :disabled="addingToCart" @click="addCart">{{ addingToCart ? '…' : 'Tambah' }}</button>
      </div>
    </template>
    <template v-else>
      <section class="hero">
        <div class="hero-copy">
          <p class="eyebrow">Cetak momen terbaikmu</p>
          <h1>Cerita yang bisa disentuh.</h1>
          <p class="description">Produk personal untuk hadiah, kenangan, dan ruang favorit. Pilih produk, atur desain, kami cetak dan kirim.</p>
          <div class="hero-actions">
            <button class="cta" type="button" @click="scrollToCatalog">Mulai buat sekarang</button>
            <button class="ghost" type="button" @click="scrollToCatalog">Lihat katalog</button>
          </div>
        </div>
        <div class="hero-art" aria-hidden="true">
          <span class="hero-card hero-card--a"></span>
          <span class="hero-card hero-card--b"></span>
          <span class="hero-card hero-card--c"></span>
        </div>
      </section>

      <section id="katalog" class="catalog">
        <div class="chips" role="tablist" aria-label="Filter kategori">
          <button class="chip" type="button" :class="{ 'is-active': !activeCategory }" @click="activeCategory = null">Semua</button>
          <button v-for="c in catalog.categories" :key="c.id" class="chip" type="button" :class="{ 'is-active': activeCategory === c.slug }" @click="activeCategory = c.slug">{{ c.name }}</button>
        </div>

        <div class="section-title catalog-title">
          <div>
            <h2>{{ activeCategoryName }}</h2>
            <p class="section-sub">Menampilkan {{ displayProducts.length }} produk</p>
          </div>
          <button v-if="activeCategory" class="ghost ghost--sm" type="button" @click="activeCategory = null">Reset filter</button>
        </div>

        <p v-if="error" class="error">{{ error }}</p>
        <div v-else-if="loading" class="products">
          <div v-for="n in 4" :key="n" class="product-card product-card--skeleton" aria-hidden="true">
            <span class="skeleton skeleton--media"></span>
            <span class="skeleton skeleton--line"></span>
            <span class="skeleton skeleton--line skeleton--short"></span>
          </div>
        </div>
        <p v-else-if="!displayProducts.length" class="empty">Belum ada produk di kategori ini.</p>
        <div v-else class="products">
          <button v-for="item in displayProducts" :key="item.id" class="product-card" type="button" @click="openProduct(item.slug)">
            <span class="product-card__media">
              <img v-if="item.coverImage" :src="item.coverImage" :alt="item.name" loading="lazy">
              <span v-else class="product-card__fallback">{{ item.category.name }}</span>
            </span>
            <span class="product-card__body">
              <span class="product-card__cat">{{ item.category.name }}</span>
              <span class="product-card__name">{{ item.name }}</span>
              <span class="product-card__price">Mulai {{ rupiah.format(item.price) }}</span>
            </span>
          </button>
        </div>
      </section>
    </template>
    </div>
    <div v-show="loginOpen" class="login-overlay" role="dialog" aria-modal="true" aria-label="Masuk dengan Google" @click.self="loginOpen = false">
      <div class="login-card">
        <button class="login-close" type="button" aria-label="Tutup" @click="loginOpen = false">×</button>
        <p class="category">Akun</p>
        <h2>Masuk ke Vita Pictura</h2>
        <p class="description">Pilih akun Google untuk melanjutkan.</p>
        <div ref="googleBtn" class="google-slot" :aria-busy="googleLoading"></div>
        <p v-if="googleLoading" class="muted">Memuat tombol Google…</p>
        <p v-if="googleError" class="error">{{ googleError }}</p>
      </div>
    </div>
    <nav v-if="!product" class="bottom-nav" aria-label="Navigasi bawah">
      <button class="bottom-nav__item" type="button" :class="{ 'is-active': !product && !cartOpen && !ordersOpen && !addressBook }" :aria-current="(!product && !cartOpen && !ordersOpen && !addressBook) ? 'page' : null" @click="goHome">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V20a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V9.5"/></svg>
        <span>Beranda</span>
      </button>
      <button class="bottom-nav__item" type="button" :class="{ 'is-active': cartOpen }" :aria-current="cartOpen ? 'page' : null" @click="openCart">
        <span class="nav-icon-wrap">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M2 3h2.2l2.4 12.2a2 2 0 0 0 2 1.6h8.9a2 2 0 0 0 2-1.6L21 7H5"/></svg>
          <span v-if="cartCount" class="badge">{{ cartCount }}</span>
        </span>
        <span>Keranjang</span>
      </button>
      <button class="bottom-nav__item" type="button" :class="{ 'is-active': ordersOpen }" :aria-current="ordersOpen ? 'page' : null" @click="openOrders">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m21 8-9-5-9 5 9 5 9-5Z"/><path d="M3 8v8l9 5 9-5V8"/><path d="M12 13v8"/></svg>
        <span>Pesanan</span>
      </button>
      <button class="bottom-nav__item" type="button" :class="{ 'is-active': addressBook }" :aria-current="addressBook ? 'page' : null" @click="customer ? openAddresses() : startGoogleLogin()">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="3.5"/><path d="M5 20a7 7 0 0 1 14 0"/></svg>
        <span>Akun</span>
      </button>
    </nav>
  </main>
</template>
