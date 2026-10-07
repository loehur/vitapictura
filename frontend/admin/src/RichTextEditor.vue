<script setup>
import { nextTick, onMounted, ref, watch } from 'vue'

const props = defineProps({
  modelValue: { type: String, default: '' },
  placeholder: { type: String, default: '' },
  minHeight: { type: Number, default: 160 },
})
const emit = defineEmits(['update:modelValue'])
const editor = ref(null)
const codeMode = ref(false)

watch(() => props.modelValue, async (value) => {
  if (codeMode.value) return
  await nextTick()
  if (editor.value && editor.value.innerHTML !== (value || '')) editor.value.innerHTML = value || ''
})

onMounted(() => {
  if (editor.value) editor.value.innerHTML = props.modelValue || ''
})

function sync() {
  emit('update:modelValue', editor.value?.innerHTML || '')
}

function command(name, value = null) {
  editor.value?.focus()
  document.execCommand(name, false, value)
  sync()
}

function addLink() {
  const url = window.prompt('URL tautan:')
  if (url?.trim()) command('createLink', url.trim())
}

function toggleCode() {
  codeMode.value = !codeMode.value
  if (!codeMode.value) {
    nextTick(() => {
      if (editor.value) editor.value.innerHTML = props.modelValue || ''
    })
  }
}
</script>

<template>
  <div class="rich-editor">
    <div class="rich-editor-toolbar" aria-label="Format teks">
      <button type="button" title="Paragraf" @click="command('formatBlock', 'p')">P</button>
      <button type="button" title="Judul" @click="command('formatBlock', 'h3')">H</button>
      <button type="button" title="Tebal" @click="command('bold')"><strong>B</strong></button>
      <button type="button" title="Miring" @click="command('italic')"><em>I</em></button>
      <button type="button" title="Daftar berpoin" @click="command('insertUnorderedList')">• List</button>
      <button type="button" title="Daftar bernomor" @click="command('insertOrderedList')">1. List</button>
      <button type="button" title="Tautan" @click="addLink">↗ Tautan</button>
      <button type="button" title="Hapus format" @click="command('removeFormat')">Tx</button>
      <button type="button" class="code-toggle" :class="{ active: codeMode }" @click="toggleCode">&lt;/&gt;</button>
    </div>
    <textarea
      v-if="codeMode"
      :value="modelValue"
      class="rich-editor-code"
      :style="{ minHeight: `${minHeight}px` }"
      placeholder="Tulis HTML di sini..."
      @input="emit('update:modelValue', $event.target.value)"
    />
    <div
      v-else
      ref="editor"
      class="rich-editor-area"
      :style="{ minHeight: `${minHeight}px` }"
      contenteditable="true"
      :data-placeholder="placeholder"
      @input="sync"
      @blur="sync"
    />
  </div>
</template>
