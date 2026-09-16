<script setup>
import { onMounted, ref } from 'vue'

const catalog = ref({ categories: [], featured: [] })
const product = ref(null)
const loading = ref(true)
const error = ref('')
const customer = ref(null)
const googleClientId = ref('')
const addressBook = ref(false)
const addresses = ref([])
const addressForm = ref({ label: '', recipient_name: '', recipient_phone: '', address_line: '', is_default: false })
const selectedOptions = ref([])
const quote = ref(null)
const uploadedFile = ref(null)
const cart = ref({ items: [], total: 0 })
const cartOpen = ref(false)
const ordersOpen = ref(false)
const orders = ref([])
const rupiah = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 })

async function request(path) {
  const url = path.startsWith('/') ? `/api${path}` : `/api/Store/Catalog/${path}`
  const response = await fetch(url, { credentials: 'include', headers: { Accept: 'application/json' } })
  const payload = await response.json()
  if (!response.ok || !payload.status) throw new Error(payload.message || 'Tidak dapat memuat katalog.')
  return payload.data
}
async function loadHome() { try { loading.value = true; catalog.value = await request('home') } catch (e) { error.value = e.message } finally { loading.value = false } }
async function loadAuth() { try { const data=await request('/Customer/Auth/config'); googleClientId.value=data.googleClientId } catch {} }
async function googleLogin(response) { try { const r=await fetch('/api/Customer/Auth/google',{method:'POST',credentials:'include',headers:{'Content-Type':'application/json',Accept:'application/json'},body:JSON.stringify({credential:response.credential})});const p=await r.json();if(!r.ok||!p.status)throw new Error(p.message);customer.value=p.data } catch(e){error.value=e.message} }
function startGoogleLogin() { if(!googleClientId.value){error.value='Login Google belum dikonfigurasi.';return} window.google?.accounts.id.initialize({client_id:googleClientId.value,callback:googleLogin});window.google?.accounts.id.prompt() }
async function openAddresses() { if(!customer.value){startGoogleLogin();return} try { const data=await request('/Customer/Addresses/index');addresses.value=data.items;addressBook.value=true } catch(e){error.value=e.message} }
async function saveAddress() { try { const r=await fetch('/api/Customer/Addresses/save',{method:'POST',credentials:'include',headers:{'Content-Type':'application/json',Accept:'application/json'},body:JSON.stringify(addressForm.value)});const p=await r.json();if(!r.ok||!p.status)throw new Error(p.message);addressForm.value={label:'',recipient_name:'',recipient_phone:'',address_line:'',is_default:false};await openAddresses() } catch(e){error.value=e.message} }
function chooseOption(group, value) { selectedOptions.value = [...selectedOptions.value.filter(id => !group.values.some(item => item.id === id)), value.id]; quote.value = null }
async function getQuote() { try { const r=await fetch('/api/Customer/Configurator/quote',{method:'POST',credentials:'include',headers:{'Content-Type':'application/json',Accept:'application/json'},body:JSON.stringify({product_id:product.value.id,option_value_ids:selectedOptions.value,quantity:1})});const p=await r.json();if(!r.ok||!p.status)throw new Error(p.message);quote.value=p.data } catch(e){error.value=e.message} }
async function uploadDesign(event) { const file=event.target.files?.[0];if(!file)return;if(!customer.value){startGoogleLogin();return}try{const form=new FormData();form.append('file',file);form.append('product_id',product.value.id);const r=await fetch('/api/Customer/Configurator/upload',{method:'POST',credentials:'include',body:form});const p=await r.json();if(!r.ok||!p.status)throw new Error(p.message);uploadedFile.value=p.data}catch(e){error.value=e.message} }
async function openCart() { if(!customer.value){startGoogleLogin();return}try{cart.value=await request('/Customer/Cart/index');cartOpen.value=true}catch(e){error.value=e.message} }
async function addCart() { if(!customer.value){startGoogleLogin();return}try{const r=await fetch('/api/Customer/Cart/add',{method:'POST',credentials:'include',headers:{'Content-Type':'application/json',Accept:'application/json'},body:JSON.stringify({product_id:product.value.id,option_value_ids:selectedOptions.value,quantity:1,upload_ids:uploadedFile.value?[uploadedFile.value.id]:[]})});const p=await r.json();if(!r.ok||!p.status)throw new Error(p.message);await openCart()}catch(e){error.value=e.message} }
async function openOrders() { if(!customer.value){startGoogleLogin();return}try{const data=await request('/Customer/Orders/index');orders.value=data.items;ordersOpen.value=true}catch(e){error.value=e.message} }
async function openProduct(slug) { try { product.value = await request(`show/${slug}`); selectedOptions.value=[];quote.value=null;uploadedFile.value=null; window.scrollTo({ top: 0, behavior: 'smooth' }) } catch (e) { error.value = e.message } }
onMounted(async()=>{await loadHome();await loadAuth()})
</script>

<template>
  <main class="app-shell">
    <header><p class="eyebrow">VITA PICTURA</p><span><button class="bag" @click="openCart">Keranjang</button><button class="bag" @click="openOrders">Pesanan</button><button class="bag" @click="customer ? openAddresses() : startGoogleLogin">{{ customer ? customer.name : 'Masuk Google' }}</button></span></header>
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
      <button class="back" @click="product = null">← Kembali ke katalog</button>
      <p class="category">{{ product.category.name }}</p><h1>{{ product.name }}</h1>
      <div class="image-placeholder">Preview produk</div><p class="price">{{ rupiah.format(product.price) }}</p><p class="description">{{ product.description }}</p>
      <section v-for="group in product.options" :key="group.id" class="options"><strong>{{ group.name }}</strong><button v-for="value in group.values" :key="value.id" :class="{ selected: selectedOptions.includes(value.id) }" @click="chooseOption(group, value)">{{ value.name }} <small v-if="value.price_delta">+{{ rupiah.format(value.price_delta) }}</small></button></section>
      <button class="cta" @click="getQuote">Hitung harga</button><p v-if="quote" class="price">Total {{ rupiah.format(quote.totalPrice) }}</p>
      <section class="upload"><strong>Unggah desain atau foto</strong><input accept="image/jpeg,image/png,application/pdf,application/zip" type="file" @change="uploadDesign"><small>JPG, PNG, PDF, atau ZIP · maksimal 50 MB</small><span v-if="uploadedFile">{{ uploadedFile.name }} siap digunakan</span></section>
      <button class="cta" @click="addCart">Tambah ke keranjang</button>
    </template>
    <template v-else>
      <section class="hero"><p class="category">Cetak momen terbaikmu</p><h1>Cerita yang bisa disentuh.</h1><p class="description">Produk personal untuk hadiah, kenangan, dan ruang favorit.</p><button class="cta">Mulai buat sekarang</button></section>
      <p v-if="loading">Memuat katalog…</p><p v-else-if="error" class="error">{{ error }}</p>
      <template v-else><section class="chips"><button v-for="c in catalog.categories" :key="c.id">{{ c.name }}</button></section><section><div class="section-title"><h2>Pilihan populer</h2><button>Lihat semua</button></div><div class="products"><button v-for="item in catalog.featured" :key="item.id" class="product" @click="openProduct(item.slug)"><div class="image-placeholder">{{ item.category.name }}</div><span>{{ item.name }}</span><strong>{{ rupiah.format(item.price) }}</strong></button></div></section></template>
    </template>
  </main>
</template>
