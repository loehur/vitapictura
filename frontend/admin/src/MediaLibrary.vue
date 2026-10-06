<script setup>
import { computed, onMounted, ref } from 'vue'
import {
  browseMedia,
  createFolder,
  deleteFile,
  deleteFolder,
  renameFile,
  renameFolder,
  uploadMediaWithProgress,
} from './mediaApi'

const folderId = ref(0)
const breadcrumb = ref([{ id: 0, name: 'Media' }])
const folders = ref([])
const files = ref([])
const loading = ref(false)
const uploading = ref(false)
const error = ref('')
const notice = ref('')
const selected = ref(null)
const viewMode = ref('grid')

// ---- Upload modal ----
const uploadOpen = ref(false)
const queue = ref([])
const dragOver = ref(false)
const fileInput = ref(null)
let seq = 0

const isEmpty = computed(() => !loading.value && folders.value.length === 0 && files.value.length === 0)
const currentFolderName = computed(() => breadcrumb.value[breadcrumb.value.length - 1]?.name || 'Media')
const pendingCount = computed(() => queue.value.filter((i) => i.status === 'queued' || i.status === 'error').length)
const doneCount = computed(() => queue.value.filter((i) => i.status === 'done').length)
const overallProgress = computed(() => {
  if (!queue.value.length) return 0
  const total = queue.value.length
  const sum = queue.value.reduce((n, i) => n + (i.status === 'done' ? 1 : (i.status === 'uploading' ? i.progress : 0)), 0)
  return Math.round((sum / total) * 100)
})

function ext(name) {
  const part = String(name || '').split('.').pop()
  return part && part !== name ? part.toUpperCase() : 'FILE'
}

function formatBytes(bytes) {
  const b = Number(bytes) || 0
  if (b < 1024) return `${b} B`
  if (b < 1048576) return `${(b / 1024).toFixed(1)} KB`
  return `${(b / 1048576).toFixed(1)} MB`
}

function toast(message, isError = false) {
  if (isError) {
    error.value = message
    notice.value = ''
  } else {
    notice.value = message
    error.value = ''
  }
  window.setTimeout(() => {
    if (notice.value === message) notice.value = ''
    if (error.value === message) error.value = ''
  }, 3200)
}

async function load(id = folderId.value) {
  loading.value = true
  error.value = ''
  selected.value = null
  try {
    const data = await browseMedia(id)
    folderId.value = data.folderId ?? id
    breadcrumb.value = data.breadcrumb || [{ id: 0, name: 'Media' }]
    folders.value = data.folders || []
    files.value = data.files || []
  } catch (e) {
    toast(e.message || 'Gagal memuat media', true)
  } finally {
    loading.value = false
  }
}

function openFolder(id) { load(id) }

async function onCreateFolder() {
  const name = window.prompt('Nama folder baru:')
  if (!name?.trim()) return
  try {
    await createFolder(name.trim(), folderId.value)
    toast('Folder dibuat')
    await load()
  } catch (e) {
    toast(e.message || 'Gagal membuat folder', true)
  }
}

async function onRenameFolder(folder) {
  const name = window.prompt('Rename folder:', folder.name)
  if (!name?.trim() || name.trim() === folder.name) return
  try {
    await renameFolder(folder.id, name.trim())
    toast('Folder diubah')
    await load()
  } catch (e) {
    toast(e.message || 'Gagal rename folder', true)
  }
}

async function onDeleteFolder(folder) {
  if (!window.confirm(`Hapus folder "${folder.name}"? Folder harus kosong.`)) return
  try {
    await deleteFolder(folder.id)
    toast('Folder dihapus')
    await load()
  } catch (e) {
    toast(e.message || 'Gagal hapus folder', true)
  }
}

// ---- Upload queue ----
function addFiles(list) {
  const items = Array.from(list || [])
  items.forEach((file) => {
    queue.value.push({
      id: ++seq,
      file,
      name: file.name,
      sizeLabel: formatBytes(file.size),
      previewUrl: file.type && file.type.startsWith('image/') ? URL.createObjectURL(file) : '',
      status: 'queued',
      progress: 0,
      error: '',
    })
  })
}

function pickFiles() {
  if (uploading.value) return
  fileInput.value?.click()
}

function onPickFiles(event) {
  addFiles(event.target.files)
  event.target.value = ''
}

function onDrop(event) {
  dragOver.value = false
  if (uploading.value) return
  addFiles(event.dataTransfer?.files)
}

function removeQueued(item) {
  if (item.previewUrl) URL.revokeObjectURL(item.previewUrl)
  queue.value = queue.value.filter((i) => i.id !== item.id)
}

function openUpload() {
  if (uploading.value) return
  queue.value.forEach((i) => { if (i.previewUrl) URL.revokeObjectURL(i.previewUrl) })
  queue.value = []
  dragOver.value = false
  uploadOpen.value = true
}

function closeUpload() {
  if (uploading.value) return
  queue.value.forEach((i) => { if (i.previewUrl) URL.revokeObjectURL(i.previewUrl) })
  queue.value = []
  uploadOpen.value = false
}

async function startUpload() {
  const pending = queue.value.filter((i) => i.status === 'queued' || i.status === 'error')
  if (!pending.length) return

  uploading.value = true
  let ok = 0
  let fail = 0

  for (const item of pending) {
    item.status = 'uploading'
    item.progress = 0
    item.error = ''
    try {
      await uploadMediaWithProgress(item.file, folderId.value, (p) => { item.progress = p })
      item.status = 'done'
      item.progress = 1
      ok++
    } catch (e) {
      item.status = 'error'
      item.error = e.message || 'Gagal'
      fail++
    }
  }

  uploading.value = false
  await load()

  if (ok && !fail) toast(`${ok} file berhasil diunggah`)
  else if (ok && fail) toast(`${ok} berhasil, ${fail} gagal`, true)
  else if (fail) toast(`${fail} file gagal diunggah`, true)
}

function selectFile(file) { selected.value = file }

async function onRenameFile(file) {
  const name = window.prompt('Rename file:', file.name)
  if (!name?.trim() || name.trim() === file.name) return
  try {
    await renameFile(file.id, name.trim())
    toast('File diubah')
    await load()
  } catch (e) {
    toast(e.message || 'Gagal rename file', true)
  }
}

async function onDeleteFile(file) {
  if (!window.confirm(`Hapus file "${file.name}"?`)) return
  try {
    await deleteFile(file.id)
    if (selected.value?.id === file.id) selected.value = null
    toast('File dihapus')
    await load()
  } catch (e) {
    toast(e.message || 'Gagal hapus file', true)
  }
}

async function copyUrl(file) {
  try {
    await navigator.clipboard.writeText(file.url)
    toast('URL disalin')
  } catch {
    window.prompt('Salin URL:', file.url)
  }
}

onMounted(() => load(0))
</script>

<template>
  <section class="media-page">
    <p v-if="notice" class="media-toast">{{ notice }}</p>
    <p v-else-if="error" class="media-toast media-toast--error">{{ error }}</p>

    <section class="panel">
      <div class="media-toolbar">
        <div class="media-toolbar__left">
          <nav class="media-breadcrumb" aria-label="Lokasi folder">
            <template v-for="(crumb, idx) in breadcrumb" :key="crumb.id">
              <button
                type="button"
                class="media-crumb"
                :class="{ 'is-current': idx === breadcrumb.length - 1 }"
                @click="openFolder(crumb.id)"
              >{{ crumb.name }}</button>
              <span v-if="idx < breadcrumb.length - 1" class="media-crumb-sep">/</span>
            </template>
          </nav>
          <p class="media-hint">{{ folders.length }} folder · {{ files.length }} file</p>
        </div>
        <div class="media-toolbar__actions">
          <div class="view-toggle">
            <button type="button" :class="{ 'is-active': viewMode === 'grid' }" @click="viewMode = 'grid'">Grid</button>
            <button type="button" :class="{ 'is-active': viewMode === 'list' }" @click="viewMode = 'list'">List</button>
          </div>
          <button type="button" class="ghost--sm" @click="onCreateFolder">+ Folder</button>
          <button type="button" class="primary primary--sm" @click="openUpload">Upload</button>
        </div>
      </div>

      <div v-if="loading" class="skeleton-list" aria-hidden="true"><span v-for="n in 6" :key="n" class="skeleton skeleton--row"></span></div>
      <p v-else-if="isEmpty" class="muted">Folder ini masih kosong. Buat folder atau unggah gambar.</p>
      <div v-else class="mlib-grid" :class="{ 'mlib-grid--list': viewMode === 'list' }">
        <article v-for="folder in folders" :key="`f-${folder.id}`" class="mlib-card mlib-card--folder">
          <button type="button" class="mlib-thumb" @click="openFolder(folder.id)" @dblclick="openFolder(folder.id)">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z"/></svg>
          </button>
          <div class="mlib-card__body">
            <button type="button" class="mlib-name" @click="openFolder(folder.id)">{{ folder.name }}</button>
            <div class="mlib-card__actions">
              <button type="button" @click="onRenameFolder(folder)">Rename</button>
              <button type="button" class="danger" @click="onDeleteFolder(folder)">Hapus</button>
            </div>
          </div>
        </article>

        <article
          v-for="file in files"
          :key="`file-${file.id}`"
          class="mlib-card"
          :class="{ 'is-selected': selected?.id === file.id }"
          @click="selectFile(file)"
        >
          <div class="mlib-thumb">
            <img v-if="file.isImage" :src="file.url" :alt="file.alt || file.name" loading="lazy">
            <span v-else class="mlib-file-icon">{{ ext(file.name) }}</span>
          </div>
          <div class="mlib-card__body">
            <p class="mlib-name" :title="file.name">{{ file.name }}</p>
            <p class="mlib-meta">{{ file.sizeLabel }}</p>
            <div class="mlib-card__actions">
              <button type="button" @click.stop="copyUrl(file)">URL</button>
              <button type="button" @click.stop="onRenameFile(file)">Rename</button>
              <button type="button" class="danger" @click.stop="onDeleteFile(file)">Hapus</button>
            </div>
          </div>
        </article>
      </div>
    </section>

    <!-- Modal Detail -->
    <div v-if="selected" class="modal-overlay" role="dialog" aria-modal="true" aria-label="Detail media" @click.self="selected = null">
      <div class="modal-card">
        <div class="modal-head">
          <h3>Detail file</h3>
          <button class="modal-x" type="button" aria-label="Tutup" @click="selected = null">×</button>
        </div>
        <div class="media-preview-box">
          <img v-if="selected.isImage" :src="selected.url" :alt="selected.name">
          <span v-else class="mlib-file-icon">{{ ext(selected.name) }}</span>
        </div>
        <dl class="media-preview-meta">
          <div><dt>Nama</dt><dd>{{ selected.name }}</dd></div>
          <div><dt>Tipe</dt><dd>{{ selected.mimeType || '—' }}</dd></div>
          <div><dt>Ukuran</dt><dd>{{ selected.sizeLabel }}</dd></div>
          <div><dt>URL</dt><dd class="media-mono">{{ selected.url }}</dd></div>
        </dl>
        <div class="modal-actions">
          <button class="ghost--sm" type="button" @click="copyUrl(selected)">Salin URL</button>
          <a class="ghost--sm" :href="selected.url" target="_blank" rel="noopener">Buka</a>
          <button class="link-danger" type="button" @click="onDeleteFile(selected)">Hapus</button>
        </div>
      </div>
    </div>

    <!-- Modal Upload -->
    <div v-if="uploadOpen" class="modal-overlay" role="dialog" aria-modal="true" aria-label="Unggah media" @click.self="closeUpload">
      <div class="modal-card media-upload">
        <div class="modal-head">
          <h3>Unggah Media</h3>
          <button class="modal-x" type="button" aria-label="Tutup" :disabled="uploading" @click="closeUpload">×</button>
        </div>

        <div
          class="upload-drop"
          :class="{ 'is-drag': dragOver, 'is-busy': uploading }"
          @click="pickFiles"
          @dragover.prevent="dragOver = true"
          @dragleave.prevent="dragOver = false"
          @drop.prevent="onDrop"
        >
          <svg class="upload-drop__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 16V4"/><path d="m7 9 5-5 5 5"/><path d="M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
          <p class="upload-drop__title">Tarik &amp; lepas file di sini</p>
          <p class="upload-drop__sub">atau klik untuk memilih · JPG, PNG, WEBP, GIF, PDF, ZIP, RAR, DOC, XLS</p>
          <input ref="fileInput" type="file" multiple accept="image/*,.pdf,.zip,.rar,.doc,.docx,.xls,.xlsx" hidden @change="onPickFiles">
        </div>

        <p class="upload-dest muted">Tujuan: <strong>{{ currentFolderName }}</strong></p>

        <div v-if="queue.length" class="upload-queue">
          <div v-for="item in queue" :key="item.id" class="upload-item" :class="`is-${item.status}`">
            <span class="upload-item__thumb">
              <img v-if="item.previewUrl" :src="item.previewUrl" :alt="item.name">
              <span v-else class="upload-item__ext">{{ ext(item.name) }}</span>
            </span>
            <span class="upload-item__meta">
              <span class="upload-item__name" :title="item.name">{{ item.name }}</span>
              <span class="upload-item__size">{{ item.sizeLabel }}</span>
              <span v-if="item.status === 'uploading'" class="upload-item__bar"><span :style="{ width: Math.round(item.progress * 100) + '%' }"></span></span>
              <span v-else-if="item.status === 'error'" class="upload-item__err">{{ item.error }}</span>
            </span>
            <span class="upload-item__state">
              <span v-if="item.status === 'queued'" class="upload-badge">menunggu</span>
              <span v-else-if="item.status === 'uploading'" class="upload-badge is-info">{{ Math.round(item.progress * 100) }}%</span>
              <span v-else-if="item.status === 'done'" class="upload-badge is-ok">✓ selesai</span>
              <span v-else class="upload-badge is-err">gagal</span>
            </span>
            <button v-if="!uploading" class="upload-item__remove" type="button" aria-label="Hapus dari daftar" @click="removeQueued(item)">×</button>
          </div>
        </div>
        <p v-else class="muted upload-empty">Belum ada file dipilih.</p>

        <div v-if="uploading" class="upload-overall">
          <div class="upload-overall__bar"><span :style="{ width: overallProgress + '%' }"></span></div>
          <span class="upload-overall__text">{{ doneCount }}/{{ queue.length }} · {{ overallProgress }}%</span>
        </div>

        <div class="modal-actions">
          <button class="ghost--sm" type="button" :disabled="uploading" @click="closeUpload">{{ !uploading && doneCount ? 'Selesai' : 'Batal' }}</button>
          <button class="primary" type="button" :disabled="uploading || !pendingCount" @click="startUpload">
            {{ uploading ? 'Mengunggah…' : `Upload ${pendingCount} file` }}
          </button>
        </div>
      </div>
    </div>
  </section>
</template>
