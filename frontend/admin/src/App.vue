<script setup>
import { computed, onMounted, ref } from 'vue'

const user = ref(null)
const orders = ref([])
const error = ref('')
const form = ref({ email: '', password: '' })
const search = ref('')
const statusFilter = ref('')
const navOpen = ref(false)
const busy = ref(false)
const savingId = ref(null)
const loading = ref(false)
const notice = ref('')
const rupiah = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 })

async function api(path, body) {
  const r = await fetch(`/api/Admin/${path}`, { method: body ? 'POST' : 'GET', credentials: 'include', headers: body ? { 'Content-Type': 'application/json' } : {}, body: body ? JSON.stringify(body) : undefined })
  const p = await r.json()
  if (!r.ok || !p.status) throw new Error(p.message)
  return p.data
}
let flashTimer
function flash(message) { notice.value = message; window.clearTimeout(flashTimer); flashTimer = window.setTimeout(() => { notice.value = '' }, 3500) }
async function login() {
  busy.value = true; error.value = ''
  try { user.value = await api('Auth/login', form.value); form.value = { email: '', password: '' }; await loadOrders(); flash('Berhasil masuk.') }
  catch (e) { error.value = e.message }
  finally { busy.value = false }
}
async function logout() { try { await api('Auth/logout', {}) } catch {} user.value = null; orders.value = []; navOpen.value = false }
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
onMounted(async () => { try { user.value = await api('Auth/me'); await loadOrders() } catch {} })
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
        <button class="nav-item is-active" type="button" aria-current="page">Pesanan</button>
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
        <h2>Pesanan</h2>
        <button class="ghost--sm" type="button" :disabled="loading" @click="loadOrders">Muat ulang</button>
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

        <p v-if="error" class="error">{{ error }}</p>
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
    </div>
  </div>
</template>
