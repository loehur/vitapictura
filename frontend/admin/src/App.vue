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
const navItems = [
  { key: 'orders', label: 'Pesanan' },
  { key: 'categories', label: 'Kategori' },
]
const titles = { orders: 'Pesanan', categories: 'Kategori' }
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
function loadView(key) { if (key === 'orders') return loadOrders(); if (key === 'categories') return loadCategories() }

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
                <tr><th>No. Pesanan</th><th>Pelanggan</th><th>Tanggal</th><th>Total</th><th>Bayar</th><th>Status</th></tr>
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
                  <td data-label="Aksi" class="row-actions">
                    <button class="link" type="button" @click="openCategory(item)">Edit</button>
                    <button class="link-danger" type="button" @click="removeCategory(item)">Hapus</button>
                  </td>
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
</template>
