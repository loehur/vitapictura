<script setup>
import { computed, onMounted, ref } from 'vue'

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

async function request(path) {
  const url = path.startsWith('/') ? `/api${path}` : `/api/Store/Catalog/${path}`
  const response = await fetch(url, { credentials: 'include', headers: { Accept: 'application/json' } })
  const payload = await response.json()
  if (!response.ok || !payload.status) throw new Error(payload.message || 'Tidak dapat memuat katalog.')
  return payload.data
}
async function loadHome() { try { loading.value = true; catalog.value = await request('home'); allProducts.value = (await request('products?limit=48')).items } catch (e) { error.value = e.message } finally { loading.value = false } }
async function loadAuth() { try { const data=await request('/Customer/Auth/config'); googleClientId.value=data.googleClientId } catch {} }
async function googleLogin(response) { try { const r=await fetch('/api/Customer/Auth/google',{method:'POST',credentials:'include',headers:{'Content-Type':'application/json',Accept:'application/json'},body:JSON.stringify({credential:response.credential})});const p=await r.json();if(!r.ok||!p.status)throw new Error(p.message);customer.value=p.data;await refreshCart() } catch(e){error.value=e.message} }
function startGoogleLogin() { if(!googleClientId.value){error.value='Login Google belum dikonfigurasi.';return} window.google?.accounts.id.initialize({client_id:googleClientId.value,callback:googleLogin});window.google?.accounts.id.prompt() }
async function openAddresses() { if(!customer.value){startGoogleLogin();return} try { const data=await request('/Customer/Addresses/index');addresses.value=data.items;addressBook.value=true } catch(e){error.value=e.message} }
async function saveAddress() { try { const r=await fetch('/api/Customer/Addresses/save',{method:'POST',credentials:'include',headers:{'Content-Type':'application/json',Accept:'application/json'},body:JSON.stringify(addressForm.value)});const p=await r.json();if(!r.ok||!p.status)throw new Error(p.message);addressForm.value={label:'',recipient_name:'',recipient_phone:'',address_line:'',is_default:false};await openAddresses() } catch(e){error.value=e.message} }
function chooseOption(group, value) { const inGroup = selectedOptions.value.filter((id) => group.values.some((item) => item.id === id)); if (inGroup.includes(value.id) && Number(group.is_required) !== 1) { selectedOptions.value = selectedOptions.value.filter((id) => id !== value.id) } else { selectedOptions.value = [...selectedOptions.value.filter(id => !group.values.some(item => item.id === id)), value.id] } quote.value = null }
async function getQuote() { try { const r=await fetch('/api/Customer/Configurator/quote',{method:'POST',credentials:'include',headers:{'Content-Type':'application/json',Accept:'application/json'},body:JSON.stringify({product_id:product.value.id,option_value_ids:selectedOptions.value,quantity:1})});const p=await r.json();if(!r.ok||!p.status)throw new Error(p.message);quote.value=p.data } catch(e){error.value=e.message} }
async function uploadDesign(event) { const file=event.target.files?.[0];if(!file)return;if(!customer.value){startGoogleLogin();return}try{const form=new FormData();form.append('file',file);form.append('product_id',product.value.id);const r=await fetch('/api/Customer/Configurator/upload',{method:'POST',credentials:'include',body:form});const p=await r.json();if(!r.ok||!p.status)throw new Error(p.message);uploadedFile.value=p.data}catch(e){error.value=e.message} }
async function openCart() { if(!customer.value){startGoogleLogin();return}try{cart.value=await request('/Customer/Cart/index');cartOpen.value=true}catch(e){error.value=e.message} }
async function addCart() { if(!customer.value){startGoogleLogin();return}try{const r=await fetch('/api/Customer/Cart/add',{method:'POST',credentials:'include',headers:{'Content-Type':'application/json',Accept:'application/json'},body:JSON.stringify({product_id:product.value.id,option_value_ids:selectedOptions.value,quantity:1,upload_ids:uploadedFile.value?[uploadedFile.value.id]:[]})});const p=await r.json();if(!r.ok||!p.status)throw new Error(p.message);await openCart()}catch(e){error.value=e.message} }
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
    <header class="site-header">
      <button class="brand" type="button" @click="goHome">
        <span class="brand-mark" aria-hidden="true">VP</span>
        <span class="brand-name">Vita Pictura</span>
      </button>
      <nav class="site-nav" aria-label="Navigasi utama">
        <button class="nav-link" type="button" @click="goHome">Katalog</button>
        <button class="nav-link" type="button" @click="openOrders">Pesanan</button>
        <button class="nav-link" type="button" @click="openCart">Keranjang<span v-if="cartCount" class="badge">{{ cartCount }}</span></button>
        <button class="nav-link account" type="button" @click="customer ? openAddresses() : startGoogleLogin">
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
    <template v-if="ordersOpen"><button class="back" @click="ordersOpen=false">← Kembali</button><p class="category">Akun</p><h1>Pesananmu</h1><p v-if="!orders.length" class="description">Belum ada pesanan.</p><article v-for="order in orders" :key="order.id" class="address"><strong>{{ order.order_number }}</strong><span>{{ order.status }} · {{ order.payment_status || 'belum dibayar' }}</span><strong>{{ rupiah.format(order.total) }}</strong><span v-if="order.tracking_number">Resi: {{ order.tracking_number }}</span></article></template>
    <template v-else-if="cartOpen">
      <button class="back" @click="cartOpen=false">← Kembali</button><p class="category">Keranjang</p><h1>Siap dicetak</h1><p v-if="!cart.items.length" class="description">Keranjangmu masih kosong.</p><article v-for="item in cart.items" :key="item.id" class="address"><strong>{{ item.name }}</strong><span v-for="choice in item.selections" :key="choice.valueId">{{ choice.group }}: {{ choice.value }}</span><strong>{{ rupiah.format(item.totalPrice) }}</strong></article><p v-if="cart.items.length" class="price">Total {{ rupiah.format(cart.total) }}</p>
    </template>
    <template v-else-if="addressBook">
      <button class="back" @click="addressBook = false">← Kembali</button><p class="category">Akun</p><h1>Alamat tersimpan</h1>
      <article v-for="item in addresses" :key="item.id" class="address"><strong>{{ item.label }} <small v-if="item.isDefault">Utama</small></strong><span>{{ item.recipientName }} · {{ item.recipientPhone }}</span><span>{{ item.addressLine }}</span></article>
      <form class="address-form" @submit.prevent="saveAddress"><h2>Tambah lokasi</h2><input v-model="addressForm.label" required placeholder="Label, contoh: Rumah"><input v-model="addressForm.recipient_name" required placeholder="Nama penerima"><input v-model="addressForm.recipient_phone" required placeholder="Nomor WhatsApp"><textarea v-model="addressForm.address_line" required placeholder="Alamat lengkap"></textarea><label><input v-model="addressForm.is_default" type="checkbox"> Jadikan alamat utama</label><button class="cta">Simpan alamat</button></form>
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

          <button class="cta product-add" type="button" @click="addCart">Tambah ke keranjang</button>
        </div>
      </div>

      <div class="product-cta-bar">
        <div>
          <span class="product-summary__label">{{ quote ? 'Total estimasi' : 'Mulai dari' }}</span>
          <strong>{{ rupiah.format(quote ? quote.totalPrice : product.price) }}</strong>
        </div>
        <button class="cta" type="button" @click="addCart">Tambah</button>
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
    <nav v-if="!product" class="bottom-nav" aria-label="Navigasi bawah">
      <button class="bottom-nav__item" type="button" :class="{ 'is-active': !product && !cartOpen && !ordersOpen && !addressBook }" @click="goHome">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V20a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V9.5"/></svg>
        <span>Beranda</span>
      </button>
      <button class="bottom-nav__item" type="button" :class="{ 'is-active': cartOpen }" @click="openCart">
        <span class="nav-icon-wrap">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M2 3h2.2l2.4 12.2a2 2 0 0 0 2 1.6h8.9a2 2 0 0 0 2-1.6L21 7H5"/></svg>
          <span v-if="cartCount" class="badge">{{ cartCount }}</span>
        </span>
        <span>Keranjang</span>
      </button>
      <button class="bottom-nav__item" type="button" :class="{ 'is-active': ordersOpen }" @click="openOrders">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m21 8-9-5-9 5 9 5 9-5Z"/><path d="M3 8v8l9 5 9-5V8"/><path d="M12 13v8"/></svg>
        <span>Pesanan</span>
      </button>
      <button class="bottom-nav__item" type="button" :class="{ 'is-active': addressBook }" @click="customer ? openAddresses() : startGoogleLogin">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="3.5"/><path d="M5 20a7 7 0 0 1 14 0"/></svg>
        <span>Akun</span>
      </button>
    </nav>
  </main>
</template>
