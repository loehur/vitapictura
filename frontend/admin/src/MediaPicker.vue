<script setup>
import { computed, onMounted, ref } from 'vue'
import { browseMedia } from './mediaApi'

const props = defineProps({ mode: { type: String, default: 'main' } })
const emit = defineEmits(['select', 'select-many', 'close'])

const folderId = ref(0)
const breadcrumb = ref([{ id: 0, name: 'Media' }])
const folders = ref([])
const files = ref([])
const loading = ref(false)
const error = ref('')
const selected = ref([])

const isMulti = computed(() => props.mode !== 'main')
const title = computed(() => {
  if (props.mode === 'gallery') return 'Tambah gambar galeri'
  if (props.mode === 'mal') return 'Pilih file dari Media'
  return 'Pilih gambar utama'
})

function ext(name) {
  const part = String(name || '').split('.').pop()
  return part && part !== name ? part.toUpperCase() : 'FILE'
}

function isSelected(file) {
  return selected.value.some((f) => f.id === file.id)
}

function onFileClick(file) {
  if (!isMulti.value) {
    emit('select', file)
    return
  }
  if (isSelected(file)) {
    selected.value = selected.value.filter((f) => f.id !== file.id)
  } else {
    selected.value = [...selected.value, file]
  }
}

function confirmMulti() {
  if (!selected.value.length) return
  emit('select-many', selected.value)
}

async function load(id = 0) {
  loading.value = true
  error.value = ''
  try {
    const data = await browseMedia(id)
    folderId.value = data.folderId ?? id
    breadcrumb.value = data.breadcrumb || [{ id: 0, name: 'Media' }]
    folders.value = data.folders || []
    const all = data.files || []
    files.value = props.mode === 'mal' ? all : all.filter((file) => file.isImage)
  } catch (e) {
    error.value = e.message || 'Gagal memuat media'
  } finally {
    loading.value = false
  }
}

onMounted(() => load(0))
</script>

<template>
  <div class="modal-overlay" role="dialog" aria-modal="true" aria-label="Pilih media" @click.self="emit('close')">
    <section class="modal-card modal-card--wide media-picker">
      <div class="modal-head">
        <h3>{{ title }}</h3>
        <button class="modal-x" type="button" aria-label="Tutup" @click="emit('close')">×</button>
      </div>

      <nav class="media-breadcrumb" aria-label="Lokasi folder">
        <template v-for="(crumb, idx) in breadcrumb" :key="crumb.id">
          <button type="button" class="media-crumb" :class="{ 'is-current': idx === breadcrumb.length - 1 }" @click="load(crumb.id)">{{ crumb.name }}</button>
          <span v-if="idx < breadcrumb.length - 1" class="media-crumb-sep">/</span>
        </template>
      </nav>

      <p v-if="isMulti" class="muted media-picker-hint">Klik beberapa file sekaligus, lalu tekan “Tambah”.</p>

      <p v-if="loading" class="muted">Memuat media…</p>
      <p v-else-if="error" class="error">{{ error }}</p>
      <div v-else class="media-picker-grid">
        <button v-for="folder in folders" :key="`f-${folder.id}`" type="button" class="media-picker-folder" @click="load(folder.id)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z"/></svg>
          <span>{{ folder.name }}</span>
        </button>
        <button
          v-for="file in files"
          :key="`file-${file.id}`"
          type="button"
          class="media-picker-file"
          :class="{ 'is-selected': isSelected(file) }"
          :title="file.name"
          @click="onFileClick(file)"
        >
          <img v-if="file.isImage" :src="file.url" :alt="file.name">
          <span v-else class="media-picker-file__badge">{{ ext(file.name) }}</span>
          <span>{{ file.name }}</span>
        </button>
        <p v-if="!folders.length && !files.length" class="muted">
          {{ mode === 'mal' ? 'Belum ada file di folder ini.' : 'Belum ada gambar di folder ini.' }}
        </p>
      </div>

      <div class="modal-actions">
        <span v-if="isMulti" class="media-picker-count muted">{{ selected.length }} dipilih</span>
        <button class="ghost--sm" type="button" @click="emit('close')">Batal</button>
        <button v-if="isMulti" class="primary" type="button" :disabled="!selected.length" @click="confirmMulti">
          Tambah{{ selected.length ? ` ${selected.length}` : '' }}
        </button>
      </div>
    </section>
  </div>
</template>
