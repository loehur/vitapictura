<script setup>
import { computed, onMounted, ref } from 'vue'
import {
  browseMedia,
  createFolder,
  deleteFile,
  deleteFolder,
  renameFile,
  renameFolder,
  uploadMedia,
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
const fileInput = ref(null)

const isEmpty = computed(() => !loading.value && folders.value.length === 0 && files.value.length === 0)

function ext(name) {
  const part = String(name || '').split('.').pop()
  return part && part !== name ? part.toUpperCase() : 'FILE'
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

function triggerUpload() { fileInput.value?.click() }

async function onUploadChange(event) {
  const list = event.target.files
  if (!list?.length) return
  uploading.value = true
  try {
    const result = await uploadMedia(list, folderId.value)
    toast(`${result.count || 1} file berhasil diunggah`)
    await load()
  } catch (e) {
    toast(e.message || 'Upload gagal', true)
  } finally {
    uploading.value = false
    event.target.value = ''
  }
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
          <button type="button" class="primary primary--sm" :disabled="uploading" @click="triggerUpload">{{ uploading ? 'Mengunggah…' : 'Upload' }}</button>
          <input ref="fileInput" type="file" multiple accept="image/*,.pdf,.zip,.rar,.doc,.docx,.xls,.xlsx" hidden @change="onUploadChange">
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
  </section>
</template>
