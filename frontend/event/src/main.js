import { createApp } from 'vue'
import App from './App.vue'
import './style.css'

// Saring noise bawaan Google Identity Services (COOP postMessage). Halaman kita
// sudah memakai Cross-Origin-Opener-Policy "same-origin-allow-popups" (lihat
// public/.htaccess); pesan ini murni warning internal GSI, tidak memengaruhi login.
const GSI_NOISE = 'Cross-Origin-Opener-Policy policy would block the window.postMessage call'
for (const level of ['warn', 'error', 'log']) {
  const original = console[level].bind(console)
  console[level] = (...args) => {
    if (args.some((a) => typeof a === 'string' && a.includes(GSI_NOISE))) return
    original(...args)
  }
}

createApp(App).mount('#app')
