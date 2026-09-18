<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  options: { type: Array, default: () => [] },
  placeholder: { type: String, default: '-' },
  disabled: { type: Boolean, default: false },
})
const emit = defineEmits(['update:modelValue'])

const open = ref(false)
const query = ref('')
const highlight = ref(0)
const root = ref(null)
const searchInput = ref(null)

const selected = computed(() => props.options.find((item) => String(item.id) === String(props.modelValue)) || null)
const filtered = computed(() => {
  const term = query.value.trim().toLowerCase()
  if (!term) return props.options
  return props.options.filter((item) => String(item.name).toLowerCase().includes(term))
})

function openDropdown() {
  if (props.disabled) return
  open.value = true
  query.value = ''
  const index = props.options.findIndex((item) => String(item.id) === String(props.modelValue))
  highlight.value = index >= 0 ? index : 0
  nextTick(() => searchInput.value?.focus())
}
function closeDropdown() { open.value = false }
function select(option) { emit('update:modelValue', option.id); closeDropdown() }
function clear() { emit('update:modelValue', ''); closeDropdown() }
function onKeydown(event) {
  if (event.key === 'Escape') return closeDropdown()
  if (event.key === 'ArrowDown') { event.preventDefault(); highlight.value = Math.min(filtered.value.length - 1, highlight.value + 1) }
  else if (event.key === 'ArrowUp') { event.preventDefault(); highlight.value = Math.max(0, highlight.value - 1) }
  else if (event.key === 'Enter') { event.preventDefault(); const option = filtered.value[highlight.value]; if (option) select(option) }
}
function onDocumentMouseDown(event) { if (root.value && !root.value.contains(event.target)) closeDropdown() }
onMounted(() => document.addEventListener('mousedown', onDocumentMouseDown))
onBeforeUnmount(() => document.removeEventListener('mousedown', onDocumentMouseDown))
watch(() => props.disabled, (value) => { if (value) closeDropdown() })
</script>

<template>
  <div ref="root" class="ss" :class="{ 'ss--open': open, 'ss--disabled': disabled }">
    <button type="button" class="ss-control" :disabled="disabled" @click="open ? closeDropdown() : openDropdown()">
      <span v-if="selected" class="ss-value">{{ selected.name }}</span>
      <span v-else class="ss-placeholder">{{ placeholder }}</span>
      <span class="ss-caret" aria-hidden="true"></span>
    </button>
    <div v-if="open" class="ss-panel">
      <input ref="searchInput" v-model="query" class="ss-search" type="text" placeholder="Cari…" @keydown="onKeydown">
      <ul class="ss-list" role="listbox">
        <li v-if="!filtered.length" class="ss-empty">Tidak ditemukan</li>
        <li
          v-for="(option, index) in filtered"
          :key="option.id"
          class="ss-option"
          :class="{ 'is-active': index === highlight, 'is-selected': String(option.id) === String(modelValue) }"
          role="option"
          @mouseenter="highlight = index"
          @click.prevent="select(option)"
        >{{ option.name }}</li>
      </ul>
      <button v-if="modelValue !== ''" type="button" class="ss-clear" @click="clear">Hapus pilihan</button>
    </div>
  </div>
</template>

<style scoped>
.ss { position: relative; }
.ss-control {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  width: 100%;
  padding: 0.85rem;
  border: 1px solid var(--color-line);
  border-radius: var(--radius-field);
  background: var(--color-surface);
  color: var(--color-ink);
  font: inherit;
  text-align: left;
  cursor: pointer;
}
.ss--disabled .ss-control { opacity: 0.6; cursor: not-allowed; }
.ss-placeholder { color: var(--color-muted); }
.ss-caret {
  flex: 0 0 auto;
  width: 0.5rem;
  height: 0.5rem;
  border-right: 2px solid var(--color-muted);
  border-bottom: 2px solid var(--color-muted);
  transform: rotate(45deg);
  transition: transform 0.15s ease;
}
.ss--open .ss-caret { transform: rotate(-135deg); }
.ss-panel {
  position: absolute;
  z-index: 40;
  left: 0;
  right: 0;
  top: calc(100% + 0.35rem);
  padding: 0.5rem;
  background: var(--color-surface);
  border: 1px solid var(--color-line);
  border-radius: var(--radius-card);
  box-shadow: var(--shadow-pop);
}
.ss-search {
  width: 100%;
  margin-bottom: 0.4rem;
  padding: 0.6rem 0.7rem;
  border: 1px solid var(--color-line);
  border-radius: var(--radius-field);
  font: inherit;
}
.ss-list { list-style: none; margin: 0; padding: 0; max-height: 13rem; overflow-y: auto; }
.ss-option { padding: 0.55rem 0.7rem; border-radius: 0.6rem; cursor: pointer; font-size: 0.92rem; }
.ss-option.is-active { background: var(--color-brand-50); }
.ss-option.is-selected { background: var(--color-brand-100); font-weight: 700; }
.ss-empty { padding: 0.6rem 0.7rem; color: var(--color-muted); font-size: 0.9rem; }
.ss-clear {
  width: 100%;
  margin-top: 0.35rem;
  padding: 0.45rem;
  border: 0;
  background: transparent;
  color: var(--color-danger);
  font-weight: 600;
  cursor: pointer;
}
</style>
