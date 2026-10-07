<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue'

const API_BASE = (import.meta.env.VITE_API_BASE_URL || '').replace(/\/+$/, '')
function apiUrl(path) { return API_BASE ? `${API_BASE}${path}` : `/api${path}` }
async function req(path) {
  const r = await fetch(apiUrl(path), { credentials: 'include', headers: { Accept: 'application/json' } })
  const p = await r.json().catch(() => ({}))
  if (!r.ok || !p.status) throw new Error(p.message || 'Gagal memuat data.')
  return p.data
}
async function post(path, body) {
  const r = await fetch(apiUrl(path), { method: 'POST', credentials: 'include', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify(body || {}) })
  const p = await r.json().catch(() => ({}))
  if (!r.ok || !p.status) throw new Error(p.message || 'Gagal memproses.')
  return p.data
}

const rupiah = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 })
function formatDate(v) { if (!v) return ''; const d = new Date(String(v).replace(' ', 'T')); return Number.isNaN(d.getTime()) ? v : d.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) }

const user = ref(null)
const booting = ref(true)
const notice = ref(''); const error = ref('')
let noticeTimer
function flash(m, isErr = false) { if (isErr) { error.value = m; notice.value = '' } else { notice.value = m; error.value = '' } clearTimeout(noticeTimer); noticeTimer = setTimeout(() => { notice.value = ''; error.value = '' }, 4000) }

const view = ref('events')
const canCreateEvents = computed(() => (user.value?.whitelistMode !== 'whitelist') || user.value?.approved === true)
const navOpen = ref(false)
const profileOpen = ref(false)
const navItems = [
  { key: 'events', label: 'Event Saya' },
  { key: 'orders', label: 'Pesanan' },
  { key: 'balance', label: 'Saldo' },
]
const navKey = computed(() => (view.value === 'event' ? 'events' : view.value))
const titles = { events: 'Event Saya', event: 'Kelola Event', orders: 'Pesanan', balance: 'Saldo' }
const currentTitle = computed(() => titles[view.value] || 'Event')
const firstName = computed(() => (user.value?.name || '').trim().split(/\s+/)[0] || '')
function switchView(key) { navOpen.value = false; go(key) }
const googleClientId = ref('')
const googleBtn = ref(null)
let googleReady = false
let googleRendered = false
let googleInited = false

async function boot() {
  booting.value = true
  try { user.value = await req('/EventUser/Auth/me') } catch { user.value = null }
  finally { booting.value = false }
}
async function loadConfig() { try { const d = await req('/EventUser/Auth/config'); googleClientId.value = d.googleClientId || '' } catch (e) { flash(e.message, true) } }
function loadGsi() {
  if (window.google?.accounts?.id) return Promise.resolve(true)
  return new Promise((resolve) => {
    if (!googleClientId.value) return resolve(false)
    const s = document.createElement('script'); s.src = 'https://accounts.google.com/gsi/client'; s.async = true; s.defer = true
    s.onload = () => resolve(true); s.onerror = () => resolve(false); document.head.appendChild(s)
  })
}
async function renderGoogle() {
  const ok = await loadGsi(); if (!ok || !window.google?.accounts?.id) { flash('Skrip Google gagal dimuat.', true); return }
  if (!googleInited) { window.google.accounts.id.initialize({ client_id: googleClientId.value, callback: onGoogle }); googleInited = true }
  await nextTick()
  if (googleBtn.value && !googleRendered) { window.google.accounts.id.renderButton(googleBtn.value, { type: 'standard', theme: 'outline', size: 'large', shape: 'pill', text: 'signin_with', locale: 'id', width: 280 }); googleRendered = true }
}
async function onGoogle(resp) {
  try { user.value = await post('/EventUser/Auth/google', { credential: resp.credential }); flash('Berhasil masuk.'); await loadEvents() } catch (e) { flash(e.message, true) }
}
async function logout() { profileOpen.value = false; try { await post('/EventUser/Auth/logout') } catch {} user.value = null; view.value = 'events' }

// ---- Events ----
const events = ref([]); const limits = ref({ maxEvents: 3, maxPhotos: 200 }); const eventsLoading = ref(false)
async function loadEvents() { eventsLoading.value = true; try { const d = await req('/EventUser/Events/index'); events.value = d.items || []; limits.value = d.limits || limits.value } catch (e) { flash(e.message, true) } finally { eventsLoading.value = false } }

const editor = ref(null) // event form
const editorIsNew = ref(false)
function blankEvent() { return { id: null, name: '', slug: '', description: '', cover_image_url: '', event_date: new Date().toISOString().slice(0, 10), default_price_standard: '', default_price_original: '', status: 'draft' } }
async function openNewEvent() { editor.value = blankEvent(); editorIsNew.value = true; photos.value = []; view.value = 'event' }
async function openEvent(ev) {
  editorIsNew.value = false
  editor.value = { ...ev, default_price_standard: ev.default_price_standard ?? '', default_price_original: ev.default_price_original ?? '' }
  await loadPhotos(ev.id); view.value = 'event'
}
async function saveEvent() {
  try { const d = await post(editor.value.id ? `/EventUser/Events/save/${editor.value.id}` : '/EventUser/Events/save', editor.value); if (!editor.value.id) { editor.value.id = d.id; editorIsNew.value = false } flash('Event disimpan.'); await loadEvents() } catch (e) { flash(e.message, true) }
}
async function removeEvent(ev) { if (!window.confirm(`Hapus event "${ev.name}"?`)) return; try { await post(`/EventUser/Events/remove/${ev.id}`, {}); await loadEvents(); view.value = 'events'; flash('Event dihapus.') } catch (e) { flash(e.message, true) } }

// ---- Photos ----
const photos = ref([]); const photosLoading = ref(false)
async function loadPhotos(eventId) { photosLoading.value = true; try { const d = await req(`/EventUser/Photos/list/${eventId}`); photos.value = d.items || [] } catch (e) { flash(e.message, true) } finally { photosLoading.value = false } }

const dragOver = ref(false); const queue = ref([]); const busy = ref(false)
function pickFiles() { fileInput.value?.click() }
const fileInput = ref(null)
function onFiles(e) { addQueue(Array.from(e.target.files || [])); if (fileInput.value) fileInput.value.value = '' }
function onDrop(e) { dragOver.value = false; addQueue(Array.from((e.dataTransfer && e.dataTransfer.files) || [])) }
function addQueue(list) {
  for (const f of list) {
    if (!/^image\//.test(f.type)) { queue.value.push({ id: Math.random().toString(36).slice(2), name: f.name, status: 'error', error: 'Bukan gambar' }); continue }
    queue.value.push({ id: Math.random().toString(36).slice(2), file: f, name: f.name, status: 'queued', progress: 0, preview: URL.createObjectURL(f) })
  }
  uploadQueue()
}
async function uploadQueue() {
  if (busy.value) return
  busy.value = true
  for (const item of queue.value) {
    if (item.status !== 'queued') continue
    try { await uploadPreview(item) ; item.status = 'done'; if (item.file) await loadPhotos(editor.value.id) }
    catch (e) { item.status = 'error'; item.error = e.message }
  }
  busy.value = false
}
function uploadPreview(item) {
  return new Promise((resolve, reject) => {
    const form = new FormData(); form.append('file', item.file)
    const xhr = new XMLHttpRequest(); xhr.open('POST', apiUrl(`/EventUser/Photos/upload_preview/${editor.value.id}`)); xhr.withCredentials = true
    xhr.upload.onprogress = (e) => { if (e.lengthComputable) item.progress = e.loaded / e.total }
    xhr.onload = () => { let p = null; try { p = JSON.parse(xhr.responseText) } catch {} ; if (xhr.status >= 200 && xhr.status < 300 && p?.status) resolve(p.data); else reject(new Error(p?.message || 'Upload gagal')) }
    xhr.onerror = () => reject(new Error('Upload gagal — koneksi terputus'))
    xhr.send(form)
  })
}
function clearDone() { queue.value = queue.value.filter((q) => q.status !== 'done') }
async function updatePhotoPrice(p) { try { await post(`/EventUser/Photos/update_price/${p.id}`, { price_standard: p.priceStandard, price_original: p.priceOriginal }); flash('Harga foto disimpan.') } catch (e) { flash(e.message, true) } }
async function removePhoto(p) { if (!window.confirm('Hapus foto ini?')) return; try { await post(`/EventUser/Photos/remove/${p.id}`, {}); await loadPhotos(editor.value.id); flash('Foto dihapus.') } catch (e) { flash(e.message, true) } }
async function setAllPrices() { try { await post(`/EventUser/Photos/set_all_prices/${editor.value.id}`, { price_standard: editor.value.default_price_standard, price_original: editor.value.default_price_original }); await loadPhotos(editor.value.id); flash('Harga semua foto diset.') } catch (e) { flash(e.message, true) } }

// ---- Orders ----
const orders = ref([]); const ordersLoading = ref(false)
async function loadOrders() { ordersLoading.value = true; try { const d = await req('/EventUser/Orders/index'); orders.value = d.items || [] } catch (e) { flash(e.message, true) } finally { ordersLoading.value = false } }
function uploadOriginal(p) {
  const input = document.createElement('input'); input.type = 'file'; input.accept = 'image/*'
  input.onchange = async () => {
    const f = input.files && input.files[0]; if (!f) return
    try { await uploadOriginalFile(p.photoId, f); flash('File original tersimpan.'); await loadOrders() } catch (e) { flash(e.message, true) }
  }
  input.click()
}
function uploadOriginalFile(photoId, file) {
  return new Promise((resolve, reject) => {
    const form = new FormData(); form.append('file', file)
    const xhr = new XMLHttpRequest(); xhr.open('POST', apiUrl(`/EventUser/Orders/upload_original/${photoId}`)); xhr.withCredentials = true
    xhr.onload = () => { let p = null; try { p = JSON.parse(xhr.responseText) } catch {}; if (xhr.status >= 200 && xhr.status < 300 && p?.status) resolve(p.data); else reject(new Error(p?.message || 'Upload gagal')) }
    xhr.onerror = () => reject(new Error('Upload gagal'))
    xhr.send(form)
  })
}

// ---- Balance ----
const balance = ref(0); const ledger = ref([]); const balanceLoading = ref(false)
async function loadBalance() { balanceLoading.value = true; try { const d = await req('/EventUser/Balance/index'); balance.value = d.balance || 0; ledger.value = d.ledger || [] } catch (e) { flash(e.message, true) } finally { balanceLoading.value = false } }

function go(v) { view.value = v; if (v === 'events') loadEvents(); if (v === 'orders') loadOrders(); if (v === 'balance') loadBalance() }

onMounted(async () => { await boot(); if (!user.value) { await loadConfig(); await renderGoogle() } else { await loadEvents() } })

// Re-render tombol Google setiap kembali ke halaman login (mis. setelah logout).
watch(user, async (u) => {
  if (u) return
  googleRendered = false
  if (!googleClientId.value) { try { await loadConfig() } catch (e) {} }
  await nextTick()
  renderGoogle()
})
</script>

<template>
  <div v-if="booting" class="auth-shell"><div class="auth-card"><p class="muted">Memuat…</p></div></div>

  <div v-else-if="!user" class="auth-shell">
    <div class="auth-card">
      <p class="eyebrow">VITA PICTURA</p>
      <h1>Event</h1>
      <p class="muted small">Masuk dengan Google untuk mengelola event foto.</p>
      <div class="google-btn"><div ref="googleBtn"></div></div>
    </div>
  </div>

  <div v-else class="admin-layout">
    <a class="skip-link" href="#main">Lewati ke konten</a>
    <aside class="sidebar" :class="{ 'is-open': navOpen }">
      <div class="sidebar__brand">
        <span class="brand-mark" aria-hidden="true">VP</span>
        <div>
          <strong>Vita Pictura</strong>
          <small>Event</small>
        </div>
      </div>
      <nav class="sidebar__nav" aria-label="Navigasi event">
        <button v-for="item in navItems" :key="item.key" class="nav-item" type="button" :class="{ 'is-active': navKey === item.key }" @click="switchView(item.key)">{{ item.label }}</button>
      </nav>
      <div class="sidebar__user">
        <button class="profile-trigger" type="button" :aria-expanded="profileOpen" aria-haspopup="menu" @click="profileOpen = !profileOpen">
          <img v-if="user.avatarUrl" class="avatar" :src="user.avatarUrl" :alt="user.name">
          <span v-else class="avatar" aria-hidden="true">{{ (user.name || 'U').charAt(0).toUpperCase() }}</span>
          <span class="profile-trigger__meta">
            <strong>{{ firstName }}</strong>
            <small>Pengelola Event</small>
          </span>
          <svg class="caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
        </button>
        <div v-if="profileOpen" class="profile-dropdown" role="menu">
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
        <button v-if="view === 'events'" class="primary" style="margin-left:auto" type="button" :disabled="events.length >= limits.maxEvents || !canCreateEvents" @click="openNewEvent">+ Event Baru</button>
        <button v-else-if="view === 'orders'" class="ghost--sm" type="button" :disabled="ordersLoading" @click="loadOrders">Muat ulang</button>
        <button v-else-if="view === 'balance'" class="ghost--sm" type="button" :disabled="balanceLoading" @click="loadBalance">Muat ulang</button>
      </header>

      <div class="alerts" aria-live="polite">
        <div v-if="notice" class="alert alert--success" role="status"><span>{{ notice }}</span><button type="button" aria-label="Tutup" @click="notice = ''">×</button></div>
        <div v-if="error" class="alert alert--error" role="alert"><span>{{ error }}</span><button type="button" aria-label="Tutup" @click="error = ''">×</button></div>
      </div>

      <!-- Event list -->
      <template v-if="view === 'events'">
        <p v-if="user.whitelistMode === 'whitelist' && !user.approved" class="card" style="border-color:#fde68a;background:#fffbeb">Akun Anda menunggu <strong>persetujuan admin</strong> sebelum dapat membuat event.</p>
        <p class="muted small">Maksimal {{ limits.maxEvents }} event per akun · {{ limits.maxPhotos }} foto per event.</p>
        <p v-if="eventsLoading" class="muted">Memuat…</p>
        <p v-else-if="!events.length" class="muted">Belum ada event. Klik “+ Event Baru”.</p>
        <div v-else class="events">
          <button v-for="ev in events" :key="ev.id" class="event-card" type="button" @click="openEvent(ev)">
            <img v-if="ev.cover_image_url" :src="ev.cover_image_url" :alt="ev.name">
            <span v-else class="ph">Event</span>
            <div class="event-card__body">
              <strong>{{ ev.name }}</strong>
              <span class="muted small">{{ formatDate(ev.event_date) }} · {{ ev.photo_count }} foto</span>
              <span class="badge">{{ ev.status }}</span>
            </div>
          </button>
        </div>
      </template>

      <!-- Event editor -->
      <template v-else-if="view === 'event' && editor">
        <div class="head">
          <button class="btn btn--ghost" type="button" @click="go('events')">← Kembali</button>
          <div class="row-actions">
            <button v-if="editor.id" class="btn btn--danger" type="button" @click="removeEvent(editor)">Hapus event</button>
            <button class="btn" type="button" @click="saveEvent">Simpan</button>
          </div>
        </div>

        <div class="card">
          <div class="grid-2">
            <label class="field"><span>Nama event</span><input v-model="editor.name" placeholder="Nama event"></label>
            <label class="field"><span>Tanggal event</span><input v-model="editor.event_date" type="date"></label>
            <label class="field"><span>Harga standar (Rp)</span><input v-model="editor.default_price_standard" type="number" min="0" placeholder="harga default"></label>
            <label class="field"><span>Harga original (Rp)</span><input v-model="editor.default_price_original" type="number" min="0" placeholder="harga default"></label>
            <label class="field"><span>Status</span><select v-model="editor.status"><option value="draft">Draft</option><option value="published">Published</option><option value="archived">Archived</option></select></label>
            <label class="field"><span>URL cover (opsional)</span><input v-model="editor.cover_image_url" placeholder="https://..."></label>
          </div>
          <label class="field"><span>Deskripsi</span><textarea v-model="editor.description"></textarea></label>
        </div>

        <template v-if="editor.id">
          <div class="head">
            <h2>Foto ({{ photos.length }}/{{ limits.maxPhotos }})</h2>
            <button class="btn btn--ghost btn--sm" type="button" @click="setAllPrices">Set harga semua (dari default)</button>
          </div>

          <div class="dropzone" :class="{ 'is-drag': dragOver, 'is-busy': busy || photos.length >= limits.maxPhotos }"
            @click="pickFiles" @dragover.prevent="dragOver = true" @dragleave.prevent="dragOver = false" @drop.prevent="onDrop">
            <svg class="dropzone__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16V4"/><path d="m7 9 5-5 5 5"/><path d="M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
            <p class="dropzone__title">Tarik &amp; lepas foto di sini</p>
            <p class="dropzone__sub">atau klik untuk memilih · JPG/PNG/WEBP/GIF/BMP · otomatis diperkecil + watermark</p>
            <input ref="fileInput" type="file" multiple accept="image/*" hidden @change="onFiles">
          </div>

          <div v-if="queue.length" class="queue">
            <div v-for="q in queue" :key="q.id" class="q-item">
              <img v-if="q.preview" :src="q.preview" alt="">
              <span v-else class="avatar">?</span>
              <div>
                <div class="q-name">{{ q.name }}</div>
                <div v-if="q.status === 'uploading' || q.status === 'queued'" class="q-bar"><span :style="{ width: Math.round((q.progress || 0) * 100) + '%' }"></span></div>
                <div v-else-if="q.status === 'error'" class="q-err">{{ q.error }}</div>
                <div v-else class="q-ok">Selesai</div>
              </div>
              <span class="muted small">{{ Math.round((q.progress || 0) * 100) }}%</span>
            </div>
            <button class="btn btn--ghost btn--sm" type="button" @click="clearDone">Bersihkan selesai</button>
          </div>

          <p v-if="photosLoading" class="muted">Memuat foto…</p>
          <div v-else class="photos">
            <div v-for="p in photos" :key="p.id" class="photo">
              <img :src="p.previewUrl" :alt="'Foto ' + p.id" loading="lazy">
              <div class="photo__body">
                <div class="photo__prices">
                  <input v-model="p.priceStandard" type="number" min="0" placeholder="Standar">
                  <input v-model="p.priceOriginal" type="number" min="0" placeholder="Original">
                </div>
                <div class="row-actions">
                  <button class="btn btn--sm" type="button" @click="updatePhotoPrice(p)">Simpan harga</button>
                  <button class="btn btn--danger btn--sm" type="button" @click="removePhoto(p)">Hapus</button>
                </div>
                <span v-if="p.originalUploaded" class="q-ok">Original tersedia</span>
                <span v-else class="muted small">Original belum diunggah</span>
              </div>
            </div>
          </div>
        </template>
        <p v-else class="muted">Simpan event dulu untuk mulai mengunggah foto.</p>
      </template>

      <!-- Orders -->
      <template v-else-if="view === 'orders'">
        <p v-if="ordersLoading" class="muted">Memuat…</p>
        <p v-else-if="!orders.length" class="muted">Belum ada pesanan.</p>
        <div v-else class="grid">
          <div v-for="o in orders" :key="o.orderId" class="card">
            <div class="head" style="margin-bottom:0.6rem"><strong>#{{ o.orderNumber }}</strong><span class="badge">{{ o.status }}</span></div>
            <div class="photos">
              <div v-for="p in o.photos" :key="p.id" class="photo">
                <img :src="p.previewUrl" :alt="'Foto ' + p.photoId" loading="lazy">
                <div class="photo__body">
                  <span class="small"><span class="badge">{{ p.variant }}</span> {{ rupiah.format(p.price) }}</span>
                  <button v-if="!p.originalUploaded" class="btn btn--sm" type="button" @click="uploadOriginal(p)">Upload file original</button>
                  <span v-else class="q-ok">Original tersedia</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </template>

      <!-- Balance -->
      <template v-else-if="view === 'balance'">
        <div class="card" style="max-width:22rem"><span class="muted small">Saldo saat ini</span><h1>{{ rupiah.format(balance) }}</h1><p class="muted small">Payout akan tersedia menyusul.</p></div>
        <h2>Riwayat</h2>
        <p v-if="balanceLoading" class="muted">Memuat…</p>
        <table v-else class="table">
          <thead><tr><th>Tanggal</th><th>Tipe</th><th>Order</th><th>Jumlah</th></tr></thead>
          <tbody>
            <tr v-for="l in ledger" :key="l.id"><td>{{ formatDate(l.created_at) }}</td><td>{{ l.type }}</td><td>{{ l.order_id || '—' }}</td><td>{{ rupiah.format(l.amount) }}</td></tr>
            <tr v-if="!ledger.length"><td colspan="4" class="muted">Belum ada transaksi.</td></tr>
          </tbody>
        </table>
      </template>
    </div>
  </div>
</template>