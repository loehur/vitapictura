<script setup>
import { onMounted, ref } from 'vue'
import { browseMedia } from './mediaApi'

defineProps({ mode: { type: String, default: 'main' } })
const emit = defineEmits(['select', 'close'])

const folderId = ref(0)
const breadcrumb = ref([{ id: 0, name: 'Media' }])
const folders = ref([])
const files = ref([])
const loading = ref(false)
const error = ref('')

async function load(id = 0) {
  loading.value = true
  error.value = ''
  try {
    const data = await browseMedia(id)
    folderId.value = data.folderId ?? id
    breadcrumb.value = data.breadcrumb || [{ id: 0, name: 'Media' }]
    folders.value = data.folders || []
    files.value = (data.files || []).filter((file) => file.isImage)
  } catch (e) {
    error.value = e.message || 'Gagal memuat media'
  } finally {
    loading.value = false
  }
}

onMounted(() => load(0))
</script>

<template>
  <div class="modal-overlay" role="dialog" aria-modal="true" aria-label="Pilih gambar" @click.self="emit('close')">
    <section class="modal-card modal-card--wide media-picker">
      <div class="modal-head">
        <h3>{{ mode === 'gallery' ? 'Tambah gambar galeri' : 'Pilih gambar utama' }}</h3>
        <button class="modal-x" type="button" aria-label="Tutup" @click="emit('close')">×</button>
      </div>

      <nav class="media-breadcrumb" aria-label="Lokasi folder">
        <template v-for="(crumb, idx) in breadcrumb" :key="crumb.id">
          <button type="button" class="media-crumb" :class="{ 'is-current': idx === breadcrumb.length - 1 }" @click="load(crumb.id)">{{ crumb.name }}</button>
          <span v-if="idx < breadcrumb.length - 1" class="media-crumb-sep">/</span>
        </template>
      </nav>

      <p v-if="loading" class="muted">Memuat media…</p>
      <p v-else-if="error" class="error">{{ error }}</p>
      <div v-else class="media-picker-grid">
        <button v-for="folder in folders" :key="`f-${folder.id}`" type="button" class="media-picker-folder" @click="load(folder.id)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z"/></svg>
          <span>{{ folder.name }}</span>
        </button>
        <button v-for="file in files" :key="`file-${file.id}`" type="button" class="media-picker-file" :title="file.name" @click="emit('select', file)">
          <img :src="file.url" :alt="file.name">
          <span>{{ file.name }}</span>
        </button>
        <p v-if="!folders.length && !files.length" class="muted">Belum ada gambar di folder ini.</p>
      </div>

      <div class="modal-actions">
        <button class="ghost--sm" type="button" @click="emit('close')">Batal</button>
      </div>
    </section>
  </div>
</template>
