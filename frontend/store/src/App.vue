<script setup>
import { computed, nextTick, onMounted, ref } from 'vue'
import SearchSelect from './SearchSelect.vue'

const catalog = ref({ categories: [], featured: [] })
const allProducts = ref([])
const activeCategory = ref(null)
const product = ref(null)
const hasTabs = computed(() => (product.value?.tabs || []).length > 0)
const loading = ref(true)
const error = ref('')
const catalogError = ref('')
const customer = ref(null)
const googleClientId = ref('')
const waNumber = ref('6285210692884')
const checkoutOpen = ref(false)
const checkoutAddressId = ref(null)
const checkoutQuote = ref(null)
const checkoutRate = ref(null)
const checkoutLoading = ref(false)
const placingOrder = ref(false)
const midtransClientKey = ref('')
const midtransProd = ref(false)
const snapReady = ref(false)
const addressBook = ref(false)
const addressModalOpen = ref(false)
const addressEditingId = ref(null)
const addresses = ref([])
const addressForm = ref({ label: '', recipient_name: '', recipient_phone: '', address_line: '', notes: '', province_id: '', province_name: '', regency_id: '', regency_name: '', district_id: '', district_name: '', village_id: '', village_name: '', postal_code: '', area_id: '', area_name: '', latitude: null, longitude: null, is_default: false })
const googleMapsKey = ref('')
const wilayah = ref({ provinces: [], regencies: [], districts: [], villages: [] })
const mapEl = ref(null)
const mapSearch = ref(null)
const areaStatus = ref('')
let mapInstance = null
let markerInstance = null
let autocompleteInstance = null
let mapsPromise = null
const optionSelection = ref({})
const manualImageKey = ref(null)
const qty = ref(1)
const noteText = ref('')
const fileMethod = ref('1')
const linkDrive = ref('')
const uploadFiles = ref([])
const uploadError = ref('')
const dragOverFiles = ref(false)
const fileInputRef = ref(null)
const uploading = ref(false)
const uploadPercent = ref(0)
const uploadIndex = ref(0)
const uploadTotal = ref(0)
const activeTab = ref(0)
const zoomUrl = ref(null)
const cart = ref({ items: [], total: 0 })
const cartOpen = ref(false)
const ordersOpen = ref(false)
const orders = ref([])
const orderDetailOpen = ref(false)
const orderDetail = ref(null)
const payingOrder = ref(false)
const notice = ref('')
const addingToCart = ref(false)
const signingIn = ref(false)
const loginOpen = ref(false)
const profileOpen = ref(false)
const googleReady = ref(false)
const googleLoading = ref(false)
const googleError = ref('')
const googleBtn = ref(null)
let googleButtonRendered = false
let modalHistory = false
let suppressPop = false
function pushModalHistory() { if (modalHistory) return; modalHistory = true; window.history.pushState({ ...(window.history.state || {}), modal: true }, '') }
function popModalHistory() { if (!modalHistory) return; modalHistory = false; suppressPop = true; window.history.back() }
function closeLoginModal() { loginOpen.value = false; popModalHistory() }
const rupiah = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 })
const cartCount = computed(() => cart.value.items.reduce((n, item) => n + (Number(item.quantity) || 0), 0) + (Number(eventCart.value.count) || 0))
const eventsOpen = ref(false)
const eventList = ref([])
const eventFrom = ref('')
const eventTo = ref('')
const eventQuery = ref('')
const eventLoading = ref(false)
const eventDetail = ref(null)
const eventCart = ref({ items: [], total: 0, count: 0 })
const eventCartCount = computed(() => Number(eventCart.value.count) || 0)
const checkoutHasProducts = ref(true)
const customerFirstName = computed(() => (customer.value?.name || '').trim().split(/\s+/)[0] || 'Masuk')
const activeCategoryName = computed(() => activeCategory.value ? (catalog.value.categories.find((c) => c.slug === activeCategory.value)?.name || 'Katalog') : 'Pilihan')
const displayProducts = computed(() => activeCategory.value ? allProducts.value.filter((p) => p.category?.slug === activeCategory.value) : allProducts.value)
const gallery = computed(() => {
  if (!product.value) return []
  if (product.value.gallery?.length) return product.value.gallery
  return product.value.coverImage ? [{ key: 'm', url: product.value.coverImage, alt: product.value.name }] : []
})
const level1Groups = computed(() => (product.value?.options || []).filter((group) => group.level === 1))
const selectedValues = computed(() => {
  if (!product.value) return []
  const chosen = []
  for (const group of product.value.options || []) {
    const valueId = optionSelection.value[group.id]
    if (!valueId) continue
    const value = group.values.find((item) => item.id === valueId)
    if (value) chosen.push(value)
  }
  return chosen
})
const activeImageKey = computed(() => 'm' + selectedValues.value.map((value) => value.imageSuffix).filter(Boolean).map((suffix) => `_${suffix}`).join(''))
const heroImage = computed(() => {
  if (manualImageKey.value) {
    const picked = gallery.value.find((item) => item.key === manualImageKey.value)
    if (picked) return picked.url
  }
  const match = gallery.value.find((item) => item.key === activeImageKey.value)
  if (match) return match.url
  return gallery.value.find((item) => item.key === 'm')?.url || gallery.value[0]?.url || product.value?.coverImage || null
})
const unitPrice = computed(() => (Number(product.value?.basePrice) || 0) + selectedValues.value.reduce((total, value) => total + (Number(value.priceDelta) || 0), 0))
const orderTotal = computed(() => unitPrice.value * (Number(qty.value) || 1))
const waLink = computed(() => `https://api.whatsapp.com/send?phone=${waNumber.value}&text=${encodeURIComponent(`Halo Vita Pictura, saya ingin informasi mengenai produk *${product.value?.name || ''}*`)}`)

const API_BASE = (import.meta.env.VITE_API_BASE_URL || '').replace(/\/+$/, '')
const MEDIA_BASE = (import.meta.env.VITE_MEDIA_BASE_URL || '').replace(/\/+$/, '')
function assetUrl(url) { if (!url) return ''; return /^https?:/i.test(url) ? url : `${MEDIA_BASE}${url}` }
function apiUrl(path) { return API_BASE ? `${API_BASE}${path}` : `/api${path}` }
function downloadUrl(id, variant) { return apiUrl('/Customer/Downloads/photo/' + id) + (variant ? '?variant=' + variant : '') }
async function request(path) {
  const url = apiUrl(path.startsWith('/') ? path : `/Store/Catalog/${path}`)
  const response = await fetch(url, { credentials: 'include', headers: { Accept: 'application/json' } })
  if (response.status === 401 && customer.value) { window.location.reload(); throw new Error('Unauthorized') }
  const payload = await response.json()
  if (!response.ok || !payload.status) throw new Error(payload.message || 'Tidak dapat memuat katalog.')
  return payload.data
}
async function post(path, body) {
  const response = await fetch(apiUrl(path), { method: 'POST', credentials: 'include', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify(body || {}) })
  if (response.status === 401 && customer.value) { window.location.reload(); throw new Error('Unauthorized') }
  const payload = await response.json()
  if (!response.ok || !payload.status) throw new Error(payload.message || 'Terjadi kesalahan.')
  return payload.data
}
let flashTimer
function flash(message) { notice.value = message; window.clearTimeout(flashTimer); flashTimer = window.setTimeout(() => { notice.value = '' }, 3500) }
async function loadHome() { try { loading.value = true; catalogError.value = ''; catalog.value = await request('home'); allProducts.value = (await request('products?limit=200')).items } catch (e) { catalogError.value = e.message } finally { loading.value = false } }
function waitForGoogle(timeout = 8000) {
  return new Promise((resolve) => {
    const started = Date.now()
    const poll = () => {
      if (window.google?.accounts?.id) return resolve(true)
      if (Date.now() - started > timeout) return resolve(false)
      window.setTimeout(poll, 150)
    }
    poll()
  })
}
function captureGsiErrors() {
  if (window.__vpGsiHooked) return
  window.__vpGsiHooked = true
  const original = console.error
  console.error = (...args) => {
    const text = args.map((a) => (a && a.message) ? a.message : String(a)).join(' ')
    if (text.includes('GSI_LOGGER')) googleError.value = text
    original.apply(console, args)
  }
}
async function ensureGoogleReady() {
  if (googleReady.value) return true
  if (!googleClientId.value) return false
  captureGsiErrors()
  googleLoading.value = true
  googleError.value = ''
  const ready = await waitForGoogle()
  googleLoading.value = false
  if (!ready) { googleError.value = 'Skrip Google gagal dimuat. Matikan adblock lalu muat ulang halaman.'; return false }
  window.google.accounts.id.initialize({ client_id: googleClientId.value, callback: googleLogin })
  googleReady.value = true
  return true
}
function renderGoogleButton() {
  if (googleButtonRendered || !googleReady.value || !googleBtn.value) return
  window.google.accounts.id.renderButton(googleBtn.value, { type: 'standard', theme: 'outline', size: 'large', shape: 'pill', text: 'signin_with', locale: 'id', width: 280 })
  googleButtonRendered = true
}
async function loadAuth() { try { const data=await request('/Customer/Auth/config'); googleClientId.value=data.googleClientId; googleMapsKey.value=data.googleMapsApiKey||''; if(data.waNumber) waNumber.value=data.waNumber; midtransClientKey.value=data.midtransClientKey||''; midtransProd.value=!!data.midtransIsProduction } catch(e){ googleError.value = e.message } }
async function googleLogin(response) { signingIn.value = true; try { customer.value = await post('/Customer/Auth/google', { credential: response.credential }); closeLoginModal(); await refreshCart(); flash('Berhasil masuk.') } catch(e){error.value=e.message} finally { signingIn.value = false } }
async function startGoogleLogin() { if(!googleClientId.value){error.value='Login Google belum dikonfigurasi.';return} loginOpen.value = true; pushModalHistory(); if(await ensureGoogleReady()){ await nextTick(); renderGoogleButton() } }
const WILAYAH_API = 'https://www.emsifa.com/api-wilayah-indonesia/api'
async function fetchWilayah(path) { const response = await fetch(`${WILAYAH_API}/${path}`); if (!response.ok) throw new Error('Gagal memuat data wilayah'); return response.json() }
function resetAddressForm() { addressForm.value = { label: '', recipient_name: '', recipient_phone: '', address_line: '', notes: '', province_id: '', province_name: '', regency_id: '', regency_name: '', district_id: '', district_name: '', village_id: '', village_name: '', postal_code: '', area_id: '', area_name: '', latitude: null, longitude: null, is_default: false }; wilayah.value.regencies = []; wilayah.value.districts = []; wilayah.value.villages = []; areaStatus.value = '' }
async function loadProvinces() { if (wilayah.value.provinces.length) return; wilayah.value.provinces = await fetchWilayah('provinces.json') }
async function loadRegencies() { const id = addressForm.value.province_id; wilayah.value.regencies = id ? await fetchWilayah(`regencies/${id}.json`) : [] }
async function loadDistricts() { const id = addressForm.value.regency_id; wilayah.value.districts = id ? await fetchWilayah(`districts/${id}.json`) : [] }
async function loadVillages() { const id = addressForm.value.district_id; wilayah.value.villages = id ? await fetchWilayah(`villages/${id}.json`) : [] }
async function onProvinceChange(id = addressForm.value.province_id) { addressForm.value.province_id = id; const item = wilayah.value.provinces.find((x) => x.id === id); addressForm.value.province_name = item?.name || ''; addressForm.value.regency_id = ''; addressForm.value.regency_name = ''; addressForm.value.district_id = ''; addressForm.value.district_name = ''; addressForm.value.village_id = ''; addressForm.value.village_name = ''; wilayah.value.regencies = []; wilayah.value.districts = []; wilayah.value.villages = []; try { await loadRegencies() } catch (e) { error.value = e.message } }
async function onRegencyChange(id = addressForm.value.regency_id) { addressForm.value.regency_id = id; const item = wilayah.value.regencies.find((x) => x.id === id); addressForm.value.regency_name = item?.name || ''; addressForm.value.district_id = ''; addressForm.value.district_name = ''; addressForm.value.village_id = ''; addressForm.value.village_name = ''; wilayah.value.districts = []; wilayah.value.villages = []; try { await loadDistricts() } catch (e) { error.value = e.message } }
async function onDistrictChange(id = addressForm.value.district_id) { addressForm.value.district_id = id; const item = wilayah.value.districts.find((x) => x.id === id); addressForm.value.district_name = item?.name || ''; addressForm.value.village_id = ''; addressForm.value.village_name = ''; wilayah.value.villages = []; try { await loadVillages() } catch (e) { error.value = e.message } }
function onVillageChange(id = addressForm.value.village_id) { addressForm.value.village_id = id; const item = wilayah.value.villages.find((x) => x.id === id); addressForm.value.village_name = item?.name || '' }
function normalizeName(value) { return String(value || '').toLowerCase().replace(/\b(kota|kabupaten|kecamatan|kelurahan|desa|kab|kec)\b\.?/g, '').replace(/[^a-z0-9]+/g, '') }
function matchByName(list, name) { const target = normalizeName(name); if (!target) return null; return list.find((x) => normalizeName(x.name) === target) || list.find((x) => { const n = normalizeName(x.name); return n && (n.includes(target) || target.includes(n)) }) || null }
function loadGoogleMaps() {
  if (window.google?.maps) return Promise.resolve(true)
  if (!googleMapsKey.value) return Promise.resolve(false)
  if (mapsPromise) return mapsPromise
  mapsPromise = new Promise((resolve) => {
    const callback = '__vpInitMaps'
    window[callback] = () => resolve(true)
    const script = document.createElement('script')
    script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(googleMapsKey.value)}&libraries=places,marker&language=id&region=ID&loading=async&callback=${callback}`
    script.async = true
    script.onerror = () => resolve(false)
    document.head.appendChild(script)
  })
  return mapsPromise
}
function setMapPoint(lat, lng) { addressForm.value.latitude = Number(lat); addressForm.value.longitude = Number(lng) }
async function applyAddressComponents(components) {
  const pick = (type) => (components || []).find((c) => c.types.includes(type))?.long_name || ''
  const province = pick('administrative_area_level_1'); const city = pick('administrative_area_level_2'); const district = pick('administrative_area_level_3'); const village = pick('administrative_area_level_4') || pick('sublocality_level_1'); const zip = pick('postal_code')
  if (zip) addressForm.value.postal_code = zip
  try {
    await loadProvinces()
    const p = matchByName(wilayah.value.provinces, province); if (!p) return
    addressForm.value.province_id = p.id; addressForm.value.province_name = p.name; await loadRegencies()
    const r = matchByName(wilayah.value.regencies, city); if (!r) return
    addressForm.value.regency_id = r.id; addressForm.value.regency_name = r.name; await loadDistricts()
    const d = matchByName(wilayah.value.districts, district); if (!d) return
    addressForm.value.district_id = d.id; addressForm.value.district_name = d.name; await loadVillages()
    const v = matchByName(wilayah.value.villages, village); if (!v) return
    addressForm.value.village_id = v.id; addressForm.value.village_name = v.name
  } catch (e) {}
}
function getBrowserLocation() {
  return new Promise((resolve) => {
    if (!navigator.geolocation) return resolve(null)
    navigator.geolocation.getCurrentPosition(
      (position) => resolve({ lat: position.coords.latitude, lng: position.coords.longitude }),
      () => resolve(null),
      { enableHighAccuracy: true, timeout: 8000, maximumAge: 0 }
    )
  })
}
function setMarkerPosition(pos) { if (!markerInstance) return; if (typeof markerInstance.setPosition === 'function') markerInstance.setPosition(pos); else markerInstance.position = pos }
function getMarkerPosition() { if (!markerInstance) return null; return typeof markerInstance.getPosition === 'function' ? markerInstance.getPosition() : markerInstance.position }
async function goToMyLocation() {
  if (!mapInstance) return
  const location = await getBrowserLocation()
  if (!location) { areaStatus.value = 'Lokasi tidak tersedia. Izinkan akses lokasi di browser.'; return }
  const position = { lat: location.lat, lng: location.lng }
  mapInstance.setCenter(position); mapInstance.setZoom(16)
  if (markerInstance) setMarkerPosition(position)
  setMapPoint(location.lat, location.lng)
}
async function startMap() {
  if (!googleMapsKey.value || !mapEl.value) return
  const ready = await loadGoogleMaps(); if (!ready || !mapEl.value) return
  const hasPoint = addressForm.value.latitude !== null && addressForm.value.longitude !== null
  const center = hasPoint ? { lat: Number(addressForm.value.latitude), lng: Number(addressForm.value.longitude) } : { lat: -0.789275, lng: 113.921327 }
  if (!mapInstance) {
    mapInstance = new google.maps.Map(mapEl.value, { center, zoom: hasPoint ? 16 : 5, mapId: 'DEMO_MAP_ID', mapTypeControl: false, streetViewControl: false, fullscreenControl: false })
    markerInstance = google.maps.marker?.AdvancedMarkerElement ? new google.maps.marker.AdvancedMarkerElement({ map: mapInstance, position: hasPoint ? center : null, gmpDraggable: true }) : new google.maps.Marker({ map: mapInstance, position: hasPoint ? center : null, draggable: true })
    const onMarkerDragEnd = () => { const p = getMarkerPosition(); if (p) setMapPoint(typeof p.lat === 'function' ? p.lat() : p.lat, typeof p.lng === 'function' ? p.lng() : p.lng) }
    if (typeof markerInstance.addEventListener === 'function') markerInstance.addEventListener('gmp-dragend', onMarkerDragEnd); else markerInstance.addListener('dragend', onMarkerDragEnd)
    mapInstance.addListener('click', (event) => { if (event.latLng) { setMarkerPosition(event.latLng); setMapPoint(event.latLng.lat(), event.latLng.lng()) } })
    if (!hasPoint) {
      getBrowserLocation().then((location) => {
        if (location && addressForm.value.latitude === null && markerInstance) {
          const position = { lat: location.lat, lng: location.lng }
          mapInstance.setCenter(position); mapInstance.setZoom(15)
          setMarkerPosition(position)
          setMapPoint(location.lat, location.lng)
        }
      })
    }
    if (mapSearch.value) {
      if (google.maps.places.PlaceAutocompleteElement) {
        const element = new google.maps.places.PlaceAutocompleteElement({ includedRegionCodes: ['id'] })
        mapSearch.value.innerHTML = ''
        mapSearch.value.appendChild(element)
        element.addEventListener('gmp-select', async (event) => {
          const place = event.placePrediction?.toPlace()
          if (!place) return
          await place.fetchFields({ fields: ['location', 'formattedAddress', 'addressComponents'] })
          const location = place.location
          if (!location) return
          mapInstance.setCenter(location); mapInstance.setZoom(16);         setMarkerPosition(location)
          setMapPoint(location.lat(), location.lng())
          if (place.formattedAddress) addressForm.value.address_line = place.formattedAddress
          const components = (place.addressComponents || []).map((c) => ({ long_name: c.longText, short_name: c.shortText, types: c.types }))
          applyAddressComponents(components)
        })
      } else if (google.maps.places.Autocomplete) {
        const input = document.createElement('input')
        input.type = 'text'; input.placeholder = 'Cari alamat / tempat'
        mapSearch.value.innerHTML = ''
        mapSearch.value.appendChild(input)
        autocompleteInstance = new google.maps.places.Autocomplete(input, { componentRestrictions: { country: 'id' }, fields: ['geometry', 'formatted_address', 'address_components', 'name'] })
        autocompleteInstance.addListener('place_changed', () => {
          const place = autocompleteInstance.getPlace()
          if (!place.geometry?.location) return
          const location = place.geometry.location
          mapInstance.setCenter(location); mapInstance.setZoom(16);         setMarkerPosition(location)
          setMapPoint(location.lat(), location.lng())
          if (place.formatted_address) addressForm.value.address_line = place.formatted_address
          applyAddressComponents(place.address_components)
        })
      }
    }
  } else {
    google.maps.event.trigger(mapInstance, 'resize')
    if (hasPoint) { mapInstance.setCenter(center); if (markerInstance) setMarkerPosition(center) }
  }
}
async function resolveArea() {
  areaStatus.value = ''
  if (!addressForm.value.village_name && !addressForm.value.district_name && !addressForm.value.regency_name) return
  try {
    const data = await post('/Customer/Addresses/lookup', { village_name: addressForm.value.village_name, district_name: addressForm.value.district_name, regency_name: addressForm.value.regency_name, province_name: addressForm.value.province_name })
    addressForm.value.area_id = data.areaId || ''
    addressForm.value.area_name = data.areaName || ''
    if (data.postalCode && !addressForm.value.postal_code) addressForm.value.postal_code = data.postalCode
    areaStatus.value = data.areaName ? `Area Biteship: ${data.areaName}` : ''
  } catch (e) { areaStatus.value = `Area Biteship belum ditentukan: ${e.message}` }
}
function addressHierarchy(item) { return [item.villageName, item.districtName, item.regencyName, item.provinceName, item.postalCode].filter(Boolean).join(', ') }
async function openAddresses() { return navigate('account') }
function openAddressModal() { addressEditingId.value = null; resetAddressForm(); if (markerInstance) setMarkerPosition(null); addressModalOpen.value = true; pushModalHistory(); nextTick(() => startMap()) }
function closeAddressModal() { addressModalOpen.value = false; addressEditingId.value = null; popModalHistory() }
async function editAddress(item) {
  addressEditingId.value = item.id
  addressForm.value = {
    label: item.label || '', recipient_name: item.recipientName || '', recipient_phone: item.recipientPhone || '',
    address_line: item.addressLine || '', notes: item.notes || '',
    province_id: item.provinceId || '', province_name: item.provinceName || '',
    regency_id: item.regencyId || '', regency_name: item.regencyName || '',
    district_id: item.districtId || '', district_name: item.districtName || '',
    village_id: item.villageId || '', village_name: item.villageName || '',
    postal_code: item.postalCode || '', area_id: item.areaId || '', area_name: item.areaName || '',
    latitude: item.latitude ?? null, longitude: item.longitude ?? null, is_default: !!item.isDefault,
  }
  try {
    if (addressForm.value.province_id) wilayah.value.regencies = await fetchWilayah(`regencies/${addressForm.value.province_id}.json`)
    if (addressForm.value.regency_id) wilayah.value.districts = await fetchWilayah(`districts/${addressForm.value.regency_id}.json`)
    if (addressForm.value.district_id) wilayah.value.villages = await fetchWilayah(`villages/${addressForm.value.district_id}.json`)
  } catch (e) {}
  addressModalOpen.value = true; pushModalHistory(); nextTick(() => startMap())
}
async function saveAddress() { try { await resolveArea(); const path = addressEditingId.value ? `/Customer/Addresses/save/${addressEditingId.value}` : '/Customer/Addresses/save'; await post(path, addressForm.value); resetAddressForm(); addressEditingId.value = null; if (markerInstance) setMarkerPosition(null); closeAddressModal(); await openAddresses(); flash('Alamat disimpan.') } catch(e){error.value=e.message} }
async function setDefaultAddress(item) { try { await post(`/Customer/Addresses/set-default/${item.id}`); await openAddresses() } catch(e){ error.value=e.message } }
async function removeAddress(item) { try { await post(`/Customer/Addresses/remove/${item.id}`); await openAddresses() } catch(e){ error.value=e.message } }
function formatDate(value) { if (!value) return ''; const date = new Date(String(value).replace(' ', 'T')); return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) }
function dependentGroups(group) { return (product.value?.options || []).filter((item) => item.level === 2 && item.parentGroupId === group.id) }
function dependentValues(group, parentGroup) { const parentId = Number(optionSelection.value[parentGroup.id]); if (!parentId) return []; const filtered = group.values.filter((value) => value.parentValueId === parentId); return filtered.length ? filtered : group.values }
function selectOption(group, rawValue) {
  const value = rawValue === '' || rawValue === null ? null : Number(rawValue)
  const next = { ...optionSelection.value }
  if (value === null) delete next[group.id]; else next[group.id] = value
  if (group.level === 1) { for (const sub of dependentGroups(group)) delete next[sub.id] }
  optionSelection.value = next
  manualImageKey.value = null
}
function changeQty(delta) { qty.value = Math.max(1, (Number(qty.value) || 1) + delta) }
const UPLOAD_EXT = ['jpg', 'jpeg', 'png', 'pdf', 'zip', 'rar']
const UPLOAD_TYPE = ['image/jpeg', 'image/png', 'application/pdf', 'application/zip', 'application/vnd.rar', 'application/x-rar-compressed']
const UPLOAD_MAX_BYTES = 50 * 1024 * 1024
function fileExt(name) { return String(name || '').split('.').pop().toLowerCase() }
function sizeLabel(bytes) { const b = Number(bytes) || 0; if (b >= 1048576) return (b / 1048576).toFixed(1) + ' MB'; if (b >= 1024) return Math.round(b / 1024) + ' KB'; return b + ' B' }
function acceptUploads(list) {
  const rejected = []
  const accepted = []
  for (const file of Array.from(list || [])) {
    if (!UPLOAD_TYPE.includes(file.type) && !UPLOAD_EXT.includes(fileExt(file.name))) { rejected.push(`${file.name} (tipe tidak diizinkan)`); continue }
    if (file.size > UPLOAD_MAX_BYTES) { rejected.push(`${file.name} (${sizeLabel(file.size)} > 50 MB)`); continue }
    accepted.push(file)
  }
  uploadFiles.value = accepted
  uploadError.value = rejected.length ? `Ditolak: ${rejected.join(', ')}` : ''
}
function onFilesChange(event) { acceptUploads(event.target.files); if (fileInputRef.value) fileInputRef.value.value = '' }
function onDropFiles(event) { dragOverFiles.value = false; acceptUploads(event.dataTransfer && event.dataTransfer.files) }
function pickFiles() { if (fileInputRef.value) fileInputRef.value.click() }
function removeUpload(index) { uploadFiles.value.splice(index, 1) }
function lockScroll(on) { if (typeof document !== 'undefined') document.body.style.overflow = on ? 'hidden' : '' }
function uploadFile(file, onProgress) {
  return new Promise((resolve, reject) => {
    const form = new FormData()
    form.append('file', file)
    form.append('product_id', product.value.id)
    const xhr = new XMLHttpRequest()
    xhr.open('POST', apiUrl('/Customer/Configurator/upload'))
    xhr.withCredentials = true
    xhr.upload.onprogress = (event) => { if (event.lengthComputable) onProgress(event.loaded / event.total) }
    xhr.onload = () => {
      let payload = {}
      try { payload = JSON.parse(xhr.responseText) } catch (e) {}
      if (xhr.status >= 200 && xhr.status < 300 && payload.status) resolve(payload.data)
      else reject(new Error(payload.message || 'Gagal mengunggah berkas.'))
    }
    xhr.onerror = () => reject(new Error('Gagal mengunggah berkas.'))
    xhr.send(form)
  })
}
async function uploadSelection() {
  const ids = []
  if (fileMethod.value !== '1' || !uploadFiles.value.length) return ids
  uploading.value = true; uploadPercent.value = 0
  uploadTotal.value = uploadFiles.value.length; uploadIndex.value = 0
  lockScroll(true)
  try {
    for (let index = 0; index < uploadFiles.value.length; index++) {
      uploadIndex.value = index + 1
      const data = await uploadFile(uploadFiles.value[index], (ratio) => {
        uploadPercent.value = Math.round(((index + ratio) / uploadFiles.value.length) * 100)
      })
      ids.push(data.id)
      uploadPercent.value = Math.round(((index + 1) / uploadFiles.value.length) * 100)
    }
    return ids
  } finally {
    uploading.value = false
    lockScroll(false)
  }
}
async function loadCart() { cart.value = await request('/Customer/Cart/index') }
async function openCart() { return navigate('cart') }
async function updateCartQty(item, quantity) { try { await post(`/Customer/Cart/update/${item.id}`, { quantity }); await loadCart() } catch(e){ error.value=e.message } }
async function removeCartItem(item) { try { await post(`/Customer/Cart/remove/${item.id}`); await loadCart(); flash('Item dihapus dari keranjang.') } catch(e){ error.value=e.message } }
async function addCart() {
  if (!customer.value) { startGoogleLogin(); return }
  const missing = level1Groups.value.find((group) => group.isRequired && !optionSelection.value[group.id])
  if (missing) { error.value = `Pilih ${missing.name} terlebih dahulu.`; return }
  addingToCart.value = true
  try {
    const uploadIds = await uploadSelection()
    let note = noteText.value.trim()
    if (fileMethod.value === '2' && linkDrive.value.trim()) note = `${note ? `${note} | ` : ''}Link Drive: ${linkDrive.value.trim()}`
    await post('/Customer/Cart/add', { product_id: product.value.id, option_value_ids: selectedValues.value.map((value) => value.id), quantity: Number(qty.value) || 1, note, upload_ids: uploadIds })
    await loadCart()
    flash('Produk ditambahkan ke keranjang.')
    product.value = null
  } catch (e) { error.value = e.message } finally { addingToCart.value = false }
}
async function openOrders() { return navigate('orders') }
async function openProduct(slug) { return navigate('product', slug) }
function resetViews() { cartOpen.value = false; ordersOpen.value = false; addressBook.value = false; addressModalOpen.value = false; checkoutOpen.value = false; orderDetailOpen.value = false; eventsOpen.value = false; eventDetail.value = null; product.value = null }
const ROUTE_PATHS = { home: '/', cart: '/keranjang', orders: '/pesanan', account: '/akun', checkout: '/checkout', events: '/event' }
function routePath(name, slug) { return name === 'product' ? `/produk/${encodeURIComponent(slug || '')}` : name === 'order' ? `/pesanan/${slug}` : name === 'event' ? `/event/${encodeURIComponent(slug || '')}` : (ROUTE_PATHS[name] || '/') }
function parseRoute(path) {
  const clean = decodeURIComponent(path || '/').replace(/\/+$/, '') || '/'
  if (clean === '/keranjang') return { name: 'cart' }
  if (clean === '/pesanan') return { name: 'orders' }
  const orderMatch = clean.match(/^\/pesanan\/(\d+)$/)
  if (orderMatch) return { name: 'order', slug: orderMatch[1] }
  if (clean === '/akun') return { name: 'account' }
  if (clean === '/checkout') return { name: 'checkout' }
  if (clean === '/event') return { name: 'events' }
  const evMatch = clean.match(/^\/event\/(.+)$/)
  if (evMatch) return { name: 'event', slug: evMatch[1] }
  const match = clean.match(/^\/produk\/(.+)$/)
  if (match) return { name: 'product', slug: match[1] }
  return { name: 'home' }
}
function pushRoute(name, slug) { const path = routePath(name, slug); if (window.location.pathname !== path) window.history.pushState({ name }, '', path) }
function requireCustomer() { if (!customer.value) { window.history.replaceState({}, '', '/'); startGoogleLogin(); return false } return true }
async function loadCartView() { if (!requireCustomer()) return false; await loadCart(); await loadEventCart(); resetViews(); cartOpen.value = true; return true }
async function loadOrdersView() { if (!requireCustomer()) return false; const data = await request('/Customer/Orders/index'); orders.value = data.items; resetViews(); ordersOpen.value = true; return true }
function safeJson(value) { try { return JSON.parse(value) } catch (e) { return [] } }
async function loadOrderView(id) {
  if (!requireCustomer()) return false
  try {
    const data = await request(`/Customer/Orders/show/${id}`)
    data.items = (data.items || []).map((it) => ({ ...it, selections: safeJson(it.selections_snapshot) }))
    orderDetail.value = data
  } catch (e) { error.value = e.message; return false }
  await loadOrdersView()
  orderDetailOpen.value = true
  pushModalHistory()
  return true
}
async function openOrderDetail(order) {
  if (!requireCustomer()) return
  try {
    const data = await request(`/Customer/Orders/show/${order.id}`)
    data.items = (data.items || []).map((it) => ({ ...it, selections: safeJson(it.selections_snapshot) }))
    orderDetail.value = data
    orderDetailOpen.value = true
    pushModalHistory()
  } catch (e) { error.value = e.message }
}
function closeOrderDetail() { orderDetailOpen.value = false; orderDetail.value = null; popModalHistory() }
const canPayOrder = computed(() => !!orderDetail.value && orderDetail.value.status === 'pending_payment' && orderDetail.value.payment_status !== 'paid')
const isHome = computed(() => !product.value && !cartOpen.value && !ordersOpen.value && !addressBook.value && !checkoutOpen.value && !eventsOpen.value && !eventDetail.value)
async function payOrder() {
  if (!orderDetail.value) return
  payingOrder.value = true
  try {
    const pay = await post(`/Customer/Payments/create/${orderDetail.value.id}`)
    await payWithSnap(pay)
    const id = orderDetail.value.id
    await loadOrders()
    await loadOrderView(id)
  } catch (e) { error.value = e.message } finally { payingOrder.value = false }
}
async function loadAccountView() { if (!requireCustomer()) return false; const data = await request('/Customer/Addresses/index'); addresses.value = data.items; resetViews(); addressBook.value = true; await nextTick(); loadProvinces().catch(() => {}); startMap(); return true }
async function loadProductView(slug) {
  const data = await request(`show/${slug}`)
  if (data.gallery) data.gallery = data.gallery.map((item) => ({ ...item, url: assetUrl(item.url) }))
  if (data.mal) data.mal = data.mal.map((item) => ({ ...item, url: assetUrl(item.url) }))
  resetViews(); product.value = data
  optionSelection.value = {}; manualImageKey.value = null; qty.value = 1; noteText.value = ''; fileMethod.value = '1'; linkDrive.value = ''; uploadFiles.value = []; uploadPercent.value = 0; activeTab.value = 0; zoomUrl.value = null
  return true
}
// ---- Events ----
async function loadEventList() {
  eventLoading.value = true
  try {
    const params = new URLSearchParams()
    if (eventFrom.value) params.set('from', eventFrom.value)
    if (eventTo.value) params.set('to', eventTo.value)
    if (eventQuery.value.trim()) params.set('q', eventQuery.value.trim())
    const qs = params.toString()
    const data = await request(`/Store/Events${qs ? `?${qs}` : ''}`)
    eventList.value = data.items || []
    if (data.from) eventFrom.value = data.from
    if (data.to) eventTo.value = data.to
  } catch (e) { error.value = e.message } finally { eventLoading.value = false }
}
async function loadEventsView() { resetViews(); eventsOpen.value = true; await loadEventList(); return true }
async function loadEventView(slug) {
  try { const data = await request(`/Store/Events/show/${encodeURIComponent(slug)}`); resetViews(); eventDetail.value = data; eventsOpen.value = true } catch (e) { error.value = e.message; return false }
  return true
}
async function openEvents() { return navigate('events') }
async function openEvent(slug) { return navigate('event', slug) }
async function loadEventCart() { try { eventCart.value = await request('/Customer/EventCart') } catch (e) { eventCart.value = { items: [], total: 0, count: 0 } } }
async function addEventPhoto(photo, variant) {
  if (!customer.value) { startGoogleLogin(); return }
  try { await post('/Customer/EventCart/add', { photo_id: photo.id, variant }); await loadEventCart(); flash('Foto ditambahkan ke keranjang.') } catch (e) { error.value = e.message }
}
async function removeEventCartGroup(group) {
  try { for (const p of group.photos) await post(`/Customer/EventCart/remove/${p.cartId}`); await loadEventCart(); flash('Foto dihapus dari keranjang.') } catch (e) { error.value = e.message }
}
async function applyRoute(route) {
  try {
    if (route.name === 'product') return await loadProductView(route.slug)
    if (route.name === 'cart') return await loadCartView()
    if (route.name === 'orders') return await loadOrdersView()
    if (route.name === 'order') return await loadOrderView(route.slug)
    if (route.name === 'account') return await loadAccountView()
    if (route.name === 'checkout') return await loadCheckoutView()
    if (route.name === 'events') return await loadEventsView()
    if (route.name === 'event') return await loadEventView(route.slug)
    resetViews(); product.value = null; return true
  } catch (e) { error.value = e.message; return false }
}
async function navigate(name, slug) { profileOpen.value = false; const ok = await applyRoute({ name, slug }); if (ok) pushRoute(name, slug); window.scrollTo({ top: 0, behavior: 'smooth' }); return ok }
function goHome() { return navigate('home') }
function scrollToCatalog() { document.getElementById('katalog')?.scrollIntoView({ behavior: 'smooth', block: 'start' }) }
async function restoreSession() { try { customer.value = await request('/Customer/Auth/me') } catch {} }
async function refreshCart() { if (!customer.value) return; try { cart.value = await request('/Customer/Cart/index') } catch {} await loadEventCart() }
async function logoutCustomer() {
  profileOpen.value = false
  try { await post('/Customer/Auth/logout') } catch (e) {}
  customer.value = null; cart.value = { items: [], total: 0 }; addresses.value = []
  await navigate('home'); flash('Kamu telah keluar dari akun.')
}
function toggleProfile() { if (!customer.value) { startGoogleLogin(); return } profileOpen.value = !profileOpen.value }
function goAccount() { profileOpen.value = false; return openAddresses() }
// ---- Checkout ----
async function loadCheckoutView() {
  if (!requireCustomer()) return false
  try { await loadCart() } catch (e) {}
  await loadEventCart()
  const hasProducts = (cart.value.items || []).length > 0
  if (hasProducts) {
    try { const data = await request('/Customer/Addresses/index'); addresses.value = data.items || [] } catch (e) { error.value = e.message; return false }
  }
  resetViews(); checkoutOpen.value = true
  checkoutQuote.value = null; checkoutRate.value = null
  checkoutHasProducts.value = hasProducts
  if (!hasProducts) { await loadQuote(); return true }
  if (!addresses.value.length) return true
  if (!checkoutAddressId.value || !addresses.value.some((a) => a.id === checkoutAddressId.value)) {
    const def = addresses.value.find((a) => a.isDefault) || addresses.value[0]
    checkoutAddressId.value = def.id
  }
  await loadQuote()
  return true
}
async function loadQuote() {
  if (checkoutHasProducts.value && !checkoutAddressId.value) { checkoutQuote.value = null; return }
  checkoutLoading.value = true
  try { checkoutQuote.value = await post('/Customer/Checkout/quote', checkoutHasProducts.value ? { address_id: checkoutAddressId.value } : {}); checkoutRate.value = null }
  catch (e) { error.value = e.message; checkoutQuote.value = null }
  finally { checkoutLoading.value = false }
}
function selectCheckoutAddress(id) { checkoutAddressId.value = id; loadQuote() }
function selectRate(rate) { checkoutRate.value = rate }
const checkoutSubtotal = computed(() => Number(checkoutQuote.value?.subtotal || 0) + Number(checkoutQuote.value?.eventSubtotal || 0))
const checkoutTotal = computed(() => checkoutSubtotal.value + Number(checkoutRate.value?.price || 0))
function openCheckout() { return navigate('checkout') }
function loadSnap() {
  if (window.snap || snapReady.value) return
  const script = document.createElement('script')
  script.src = (midtransProd.value ? 'https://app.midtrans.com' : 'https://app.sandbox.midtrans.com') + '/snap/snap.js'
  script.setAttribute('data-client-key', midtransClientKey.value)
  script.onload = () => { snapReady.value = true }
  document.head.appendChild(script)
}
function waitForSnap(timeout = 8000) { return new Promise((resolve) => { const started = Date.now(); const poll = () => { if (window.snap) return resolve(true); if (Date.now() - started > timeout) return resolve(false); window.setTimeout(poll, 150) }; poll() }) }
async function payWithSnap(pay) {
  loadSnap()
  const ready = await waitForSnap()
  if (!ready || !window.snap) { if (pay.redirectUrl) { window.location.href = pay.redirectUrl; return } error.value = 'Gagal memuat pembayaran Midtrans.'; return }
  await new Promise((resolve) => {
    window.snap.pay(pay.token, {
      onSuccess: () => { flash('Pembayaran berhasil.'); resolve() },
      onPending: () => { flash('Menunggu pembayaran.'); resolve() },
      onError: () => { error.value = 'Pembayaran gagal.'; resolve() },
      onClose: () => { flash('Pembayaran belum diselesaikan.'); resolve() },
    })
  })
}
async function placeOrder() {
  if (checkoutHasProducts.value && !checkoutRate.value) { error.value = 'Pilih kurir terlebih dahulu.'; return }
  placingOrder.value = true
  try {
    const body = checkoutHasProducts.value ? { address_id: checkoutAddressId.value, courier_company: checkoutRate.value.company, courier_type: checkoutRate.value.type } : {}
    const order = await post('/Customer/Checkout/create', body)
    await loadCart(); await loadEventCart()
    const pay = await post(`/Customer/Payments/create/${order.id}`)
    checkoutOpen.value = false; checkoutQuote.value = null; checkoutRate.value = null
    await payWithSnap(pay)
    await navigate('orders')
  } catch (e) { error.value = e.message } finally { placingOrder.value = false }
}

onMounted(async()=>{await loadHome();await loadAuth();await restoreSession();await refreshCart();window.addEventListener('popstate',()=>{if(suppressPop){suppressPop=false;return}if(loginOpen.value){loginOpen.value=false;modalHistory=false;return}if(addressModalOpen.value){addressModalOpen.value=false;modalHistory=false;return}if(orderDetailOpen.value){orderDetailOpen.value=false;orderDetail.value=null;modalHistory=false;return}applyRoute(parseRoute(window.location.pathname))});await applyRoute(parseRoute(window.location.pathname))})
</script>

<template>
  <header class="site-header">
    <button class="brand" type="button" @click="goHome">
      <span class="brand-mark" aria-hidden="true">VP</span>
      <span class="brand-name">Vita Pictura</span>
    </button>
    <nav class="site-nav" aria-label="Navigasi utama">
      <button class="nav-link" type="button" :class="{ 'is-active': isHome }" @click="goHome">Catalog</button>
      <button class="nav-link" type="button" :class="{ 'is-active': eventsOpen }" @click="openEvents">Events</button>
      <button class="nav-link" type="button" :class="{ 'is-active': ordersOpen }" @click="openOrders">Orders</button>
      <button class="nav-link" type="button" :class="{ 'is-active': cartOpen }" @click="openCart">Cart<span v-if="cartCount" class="badge">{{ cartCount }}</span></button>
    </nav>
    <div class="header-actions">
      <div class="profile-menu">
        <button class="nav-link account" type="button" :aria-haspopup="customer ? 'menu' : null" :aria-expanded="profileOpen" @click="toggleProfile">
          <img v-if="customer && customer.avatarUrl" class="avatar" :src="customer.avatarUrl" :alt="customer.name">
          <span v-else class="avatar avatar--fallback" aria-hidden="true">{{ (customer ? customer.name : 'M').charAt(0).toUpperCase() }}</span>
          <span class="account-name">{{ customerFirstName }}</span>
          <svg v-if="customer" class="caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
        </button>
        <div v-if="profileOpen" class="profile-dropdown" role="menu">
          <button class="profile-dropdown__item" type="button" role="menuitem" @click="goAccount">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s-7-6.2-7-11a7 7 0 1 1 14 0c0 4.8-7 11-7 11Z"/><circle cx="12" cy="10" r="2.5"/></svg>
            <span>Addresses</span>
          </button>
          <button class="profile-dropdown__item profile-dropdown__item--danger" type="button" role="menuitem" @click="logoutCustomer">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/></svg>
            <span>Logout</span>
          </button>
        </div>
      </div>
      <button class="mobile-cart" type="button" aria-label="Keranjang" @click="openCart">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M2 3h2.2l2.4 12.2a2 2 0 0 0 2 1.6h8.9a2 2 0 0 0 2-1.6L21 7H5"/></svg>
        <span v-if="cartCount" class="badge">{{ cartCount }}</span>
      </button>
    </div>
  </header>

  <main class="app-shell">
    <a class="skip-link" href="#main">Lewati ke konten</a>
    <div v-if="profileOpen" class="profile-scrim" @click="profileOpen = false"></div>
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
    <div id="main" tabindex="-1">
    <template v-if="ordersOpen">
      <p class="category">Pesanan</p>

      <div v-if="!orders.length" class="state">
        <p class="description">Belum ada pesanan.</p>
        <button class="cta" type="button" @click="goHome">Mulai belanja</button>
      </div>

      <div v-else class="orders">
        <article v-for="order in orders" :key="order.id" class="order-card" role="button" tabindex="0" @click="openOrderDetail(order)" @keydown.enter="openOrderDetail(order)">
          <div class="order-card__head">
            <strong>{{ order.order_number }}</strong>
            <span class="status" :class="`status--${order.status}`">{{ order.status }}</span>
          </div>
          <span class="order-card__meta">{{ formatDate(order.created_at) }}</span>
          <div class="order-card__row">
            <span class="status" :class="`status--${order.payment_status || 'unpaid'}`">{{ order.payment_status || 'belum dibayar' }}</span>
            <strong>{{ rupiah.format(order.total) }}</strong>
          </div>
          <span v-if="order.tracking_number" class="order-card__meta">Resi: {{ order.tracking_number }}</span>
        </article>
      </div>

      <!-- Modal detail pesanan -->
      <div v-if="orderDetailOpen" class="modal-overlay" role="dialog" aria-modal="true" aria-label="Detail pesanan" @click.self="closeOrderDetail">
        <div class="modal-card">
          <button class="modal-close" type="button" aria-label="Tutup" @click="closeOrderDetail">×</button>
          <p class="category">Pesanan</p>
          <h2>{{ orderDetail?.order_number || 'Detail pesanan' }}</h2>
          <div class="od-status">
            <span class="status" :class="`status--${orderDetail?.status}`">{{ orderDetail?.status }}</span>
            <span class="status" :class="`status--${orderDetail?.payment_status || 'unpaid'}`">{{ orderDetail?.payment_status || 'belum dibayar' }}</span>
          </div>
          <p class="order-card__meta">{{ formatDate(orderDetail?.created_at) }}</p>

          <section class="co-section od-items">
            <div v-for="(it, i) in orderDetail?.items || []" :key="i" class="od-item">
              <strong>{{ it.product_name }}</strong>
              <template v-if="it.item_type === 'event_photo'">
                <span class="cart-item__choice">{{ (it.photos || []).length }} foto</span>
                <div class="od-photos">
                  <div v-for="p in it.photos" :key="p.id" class="od-photo">
                    <img :src="p.previewUrl" :alt="'Foto ' + p.photoId" loading="lazy" draggable="false">
                    <div class="od-photo__row">
                      <small>{{ p.variant }}</small>
                      <a v-if="p.canDownloadStandard" class="link" :href="downloadUrl(p.id, p.variant === 'original' ? 'standard' : '')" target="_blank" rel="noopener">Unduh standar</a>
                    </div>
                    <div v-if="p.variant === 'original'" class="od-photo__row">
                      <a v-if="p.canDownloadOriginal" class="link" :href="downloadUrl(p.id, 'original')" target="_blank" rel="noopener">Unduh original</a>
                      <small v-else class="muted">Original menunggu diunggah</small>
                    </div>
                  </div>
                </div>
              </template>
              <template v-else>
                <span v-for="s in it.selections" :key="s.valueId" class="cart-item__choice">{{ s.group }}: {{ s.value }}</span>
                <span v-if="it.note" class="cart-item__note">Catatan: {{ it.note }}</span>
                <div class="od-item__row"><span>{{ it.quantity }} pcs</span><strong>{{ rupiah.format(it.total_price) }}</strong></div>
              </template>
            </div>
          </section>

          <section v-if="orderDetail?.recipient" class="co-section co-summary">
            <div><span>Penerima</span><strong>{{ orderDetail.recipient.recipientName }}</strong></div>
            <div><span>Telepon</span><strong>{{ orderDetail.recipient.recipientPhone }}</strong></div>
            <div><span>Alamat</span><strong>{{ orderDetail.recipient.addressLine }}</strong></div>
            <div v-if="orderDetail.recipient.notes"><span>Catatan</span><strong>{{ orderDetail.recipient.notes }}</strong></div>
          </section>

          <section class="co-section co-summary">
            <div><span>Subtotal</span><strong>{{ rupiah.format(orderDetail?.subtotal || 0) }}</strong></div>
            <div><span>Ongkir</span><strong>{{ rupiah.format(orderDetail?.shipping_cost || 0) }}</strong></div>
            <div class="co-total"><span>Total</span><strong>{{ rupiah.format(orderDetail?.total || 0) }}</strong></div>
          </section>

          <section v-if="orderDetail?.tracking_number" class="co-section co-summary">
            <div><span>Resi</span><strong>{{ orderDetail.tracking_number }}</strong></div>
          </section>

          <button v-if="canPayOrder" class="cta" type="button" :disabled="payingOrder" @click="payOrder">{{ payingOrder ? 'Memproses…' : 'Bayar sekarang' }}</button>
        </div>
      </div>
    </template>
    <template v-else-if="eventDetail">
      <button class="back link" type="button" @click="navigate('events')">← Semua Event</button>

      <div class="ev-hero">
        <p class="eyebrow">Event</p>
        <h1>{{ eventDetail.name }}</h1>
        <p class="ev-hero__meta">{{ formatDate(eventDetail.event_date) }}<template v-if="eventDetail.owner_name"> · {{ eventDetail.owner_name }}</template></p>
        <p v-if="eventDetail.description" class="description">{{ eventDetail.description }}</p>
      </div>

      <section class="ev-panel">
        <div class="ev-panel__head">
          <strong>Galeri Foto</strong>
          <span class="ev-count">{{ eventDetail.photos.length }} foto</span>
        </div>
        <p v-if="!eventDetail.photos.length" class="description">Belum ada foto pada event ini.</p>
        <div v-else class="ev-photos">
          <div v-for="ph in eventDetail.photos" :key="ph.id" class="ev-photo">
            <div class="ev-photo__media">
              <img :src="ph.previewUrl" :alt="'Foto ' + ph.id" loading="lazy" draggable="false" @contextmenu.prevent>
            </div>
            <div class="ev-photo__actions">
              <button v-if="ph.priceStandard != null" class="cta" type="button" @click="addEventPhoto(ph, 'standard')">Standar {{ rupiah.format(ph.priceStandard) }}</button>
              <button v-if="ph.priceOriginal != null" class="ghost" type="button" @click="addEventPhoto(ph, 'original')">Original {{ rupiah.format(ph.priceOriginal) }}</button>
            </div>
          </div>
        </div>
      </section>
    </template>
    <template v-else-if="eventsOpen">
      <div class="ev-hero">
        <p class="eyebrow">Event</p>
        <h1>Jelajahi Event</h1>
        <p class="description">Temukan momen Anda. Cari event berdasarkan rentang tanggal (maksimal 7 hari).</p>
      </div>

      <section class="ev-panel">
        <div class="ev-search">
          <label class="field"><span>Dari</span><input v-model="eventFrom" type="date"></label>
          <label class="field"><span>Sampai</span><input v-model="eventTo" type="date"></label>
          <label class="field ev-search__q"><span>Cari</span><input v-model="eventQuery" type="search" placeholder="Nama event" @keyup.enter="loadEventList"></label>
          <button class="cta ev-search__btn" type="button" @click="loadEventList">Cari Event</button>
        </div>
        <p class="section-sub">Rentang maksimal 7 hari (default 7 hari terakhir).</p>
      </section>

      <p v-if="eventLoading" class="description ev-state">Memuat event…</p>
      <p v-else-if="!eventList.length" class="description ev-state">Tidak ada event pada rentang ini.</p>
      <div v-else class="ev-grid">
        <button v-for="ev in eventList" :key="ev.id" class="ev-card" type="button" @click="openEvent(ev.slug)">
          <span class="ev-card__media">
            <img v-if="ev.cover_image_url" :src="assetUrl(ev.cover_image_url)" :alt="ev.name" loading="lazy">
            <span v-else class="ev-card__ph">Event</span>
          </span>
          <span class="ev-card__body">
            <strong>{{ ev.name }}</strong>
            <span class="ev-card__meta">{{ formatDate(ev.event_date) }} · {{ ev.photo_count }} foto</span>
          </span>
        </button>
      </div>
    </template>
    <template v-else-if="cartOpen">
      <p class="category">Keranjang</p>

      <div v-if="!cart.items.length && !eventCart.items.length" class="state">
        <p class="description">Keranjangmu masih kosong.</p>
        <button class="cta" type="button" @click="goHome">Mulai belanja</button>
      </div>

      <div v-else class="cart">
        <article v-for="item in cart.items" :key="item.id" class="cart-item">
          <div v-if="item.image" class="cart-item__media">
            <img :src="item.image" :alt="item.name">
          </div>
          <div class="cart-item__body">
            <strong>{{ item.name }}</strong>
            <span v-for="choice in item.selections" :key="choice.valueId" class="cart-item__choice">{{ choice.group }}: {{ choice.value }}</span>
            <span v-if="item.note" class="cart-item__note">Catatan: {{ item.note }}</span>
            <div v-if="item.uploads && item.uploads.length" class="cart-item__files">
              <span class="cart-item__files-label">File:</span>
              <a v-for="file in item.uploads" :key="file.id" :href="file.url" target="_blank" rel="noopener">{{ file.name }}</a>
            </div>
            <div class="cart-item__row">
              <div class="cart-item__controls">
                <div class="qty">
                  <button type="button" aria-label="Kurangi jumlah" @click="updateCartQty(item, Math.max(1, item.quantity - 1))">−</button>
                  <span>{{ item.quantity }}</span>
                  <button type="button" aria-label="Tambah jumlah" @click="updateCartQty(item, item.quantity + 1)">+</button>
                </div>
                <button class="icon-danger" type="button" aria-label="Hapus item" title="Hapus" @click="removeCartItem(item)">
                  <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg>
                </button>
              </div>
              <strong>{{ rupiah.format(item.totalPrice) }}</strong>
            </div>
          </div>
        </article>

        <article v-for="grp in eventCart.items" :key="'ev' + grp.eventId" class="cart-item">
          <div class="cart-item__body">
            <strong>{{ grp.eventName }} <span class="cart-item__choice">{{ grp.qty }} foto</span></strong>
            <div class="cart-item__event-photos">
              <img v-for="p in grp.photos" :key="p.cartId" :src="p.previewUrl" :alt="'Foto ' + p.photoId" loading="lazy" draggable="false">
            </div>
            <div class="cart-item__row">
              <button class="icon-danger" type="button" aria-label="Hapus foto event" title="Hapus" @click="removeEventCartGroup(grp)">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg>
              </button>
              <strong>{{ rupiah.format(grp.subtotal) }}</strong>
            </div>
          </div>
        </article>

        <div class="cart-total">
          <span>Total</span>
          <strong>{{ rupiah.format(Number(cart.total) + Number(eventCart.total)) }}</strong>
        </div>
        <button class="cta" type="button" @click="openCheckout">Checkout</button>
      </div>
    </template>
    <template v-else-if="addressBook">
      <p class="category">Daftar Alamat</p>
      <button class="cta" type="button" @click="openAddressModal">+ Tambah lokasi</button>

      <p v-if="!addresses.length" class="description">Belum ada alamat tersimpan.</p>
      <article v-for="item in addresses" :key="item.id" class="address">
        <div class="address__head">
          <strong>{{ item.label }}</strong>
          <small v-if="item.isDefault">Utama</small>
        </div>
        <span>{{ item.recipientName }} · {{ item.recipientPhone }}</span>
        <span>{{ item.addressLine }}</span>
        <span v-if="addressHierarchy(item)" class="address__area">{{ addressHierarchy(item) }}</span>
        <div class="address__actions">
          <button class="link" type="button" @click="editAddress(item)">Edit</button>
          <button v-if="!item.isDefault" class="link" type="button" @click="setDefaultAddress(item)">Jadikan utama</button>
          <button class="link-danger" type="button" @click="removeAddress(item)">Hapus</button>
        </div>
      </article>

      <div v-show="addressModalOpen" class="modal-overlay" role="dialog" aria-modal="true" :aria-label="addressEditingId ? 'Edit lokasi' : 'Tambah lokasi'" @click.self="closeAddressModal">
      <div class="modal-card">
      <button class="modal-close" type="button" aria-label="Tutup" @click="closeAddressModal">×</button>
      <form class="address-form" @submit.prevent="saveAddress">
        <h2>{{ addressEditingId ? 'Edit lokasi' : 'Tambah lokasi' }}</h2>
        <label class="field"><span>Label</span><input v-model="addressForm.label" required placeholder="Contoh: Rumah"></label>
        <label class="field"><span>Nama penerima</span><input v-model="addressForm.recipient_name" required placeholder="Nama lengkap"></label>
        <label class="field"><span>Nomor WhatsApp</span><input v-model="addressForm.recipient_phone" required placeholder="08xxxxxxxxxx"></label>

        <div class="field-grid">
          <div class="field"><span>Provinsi</span>
            <SearchSelect :model-value="addressForm.province_id" :options="wilayah.provinces" @update:model-value="onProvinceChange" />
          </div>
          <div class="field"><span>Kota/Kabupaten</span>
            <SearchSelect :model-value="addressForm.regency_id" :options="wilayah.regencies" :disabled="!addressForm.province_id" @update:model-value="onRegencyChange" />
          </div>
          <div class="field"><span>Kecamatan</span>
            <SearchSelect :model-value="addressForm.district_id" :options="wilayah.districts" :disabled="!addressForm.regency_id" @update:model-value="onDistrictChange" />
          </div>
          <div class="field"><span>Kelurahan/Desa</span>
            <SearchSelect :model-value="addressForm.village_id" :options="wilayah.villages" :disabled="!addressForm.district_id" @update:model-value="onVillageChange" />
          </div>
        </div>

        <label class="field"><span>Kode pos</span><input v-model="addressForm.postal_code" placeholder="28111"></label>

        <div class="field">
          <span>Titik lokasi (Google Maps)</span>
          <div ref="mapSearch" class="map-search"></div>
          <div class="map-wrap">
            <div ref="mapEl" class="map-box"></div>
            <button type="button" class="map-loc-btn" title="Titik Saya" aria-label="Titik Saya" @click="goToMyLocation">
              <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="7"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/><circle cx="12" cy="12" r="2.2" fill="currentColor" stroke="none"/></svg>
            </button>
          </div>
          <small v-if="!googleMapsKey" class="muted">Google Maps API key belum diatur.</small>
          <small v-else-if="addressForm.latitude" class="muted">Titik: {{ addressForm.latitude }}, {{ addressForm.longitude }}</small>
        </div>

        <label class="field"><span>Alamat lengkap</span><textarea v-model="addressForm.address_line" required placeholder="Jalan, nomor, patokan"></textarea></label>
        <label class="field"><span>Catatan (opsional)</span><input v-model="addressForm.notes" placeholder="Patokan, jam kirim, dll"></label>
        <label class="check"><input v-model="addressForm.is_default" type="checkbox"> Jadikan alamat utama</label>
        <p v-if="areaStatus" class="muted">{{ areaStatus }}</p>
        <button class="cta" type="submit">{{ addressEditingId ? 'Simpan perubahan' : 'Simpan alamat' }}</button>
      </form>
      </div>
      </div>
    </template>
    <template v-else-if="checkoutOpen">
      <p class="category">Checkout</p>
      <h1>Konfirmasi pesanan</h1>

      <section v-if="checkoutHasProducts" class="co-section">
        <div class="co-head"><strong>Alamat pengiriman</strong><button class="link" type="button" @click="navigate('account')">Kelola alamat</button></div>
        <p v-if="!addresses.length" class="description">Belum ada alamat. Tambahkan alamat terlebih dahulu.</p>
        <label v-for="a in addresses" :key="a.id" class="co-address" :class="{ 'is-active': checkoutAddressId === a.id }">
          <input type="radio" name="co-address" :value="a.id" :checked="checkoutAddressId === a.id" @change="selectCheckoutAddress(a.id)">
          <span>
            <strong>{{ a.label }}<template v-if="a.isDefault"> · Utama</template></strong>
            <small>{{ a.recipientName }} · {{ a.recipientPhone }}</small>
            <small>{{ a.addressLine }}</small>
          </span>
        </label>
      </section>

      <section v-if="checkoutHasProducts" class="co-section">
        <div class="co-head"><strong>Pengiriman</strong><span v-if="checkoutQuote" class="co-head__meta">{{ checkoutQuote.rates.length }} layanan</span></div>
        <p v-if="checkoutLoading" class="description">Menghitung ongkir…</p>
        <p v-else-if="!checkoutQuote" class="description">Pilih alamat untuk melihat ongkir.</p>
        <p v-else-if="!checkoutQuote.rates.length" class="description">Tidak ada layanan kurir untuk alamat ini.</p>
        <template v-else>
          <div class="co-rates" :class="{ 'co-rates--scroll': checkoutQuote.rates.length > 3 }">
            <label v-for="(r, i) in checkoutQuote.rates" :key="i" class="co-rate" :class="{ 'is-active': checkoutRate === r }">
              <input type="radio" name="co-rate" :checked="checkoutRate === r" @change="selectRate(r)">
              <span class="co-rate__name">{{ r.courier_name }} {{ r.courier_service_name }}</span>
              <strong>{{ rupiah.format(r.price) }}</strong>
            </label>
          </div>
        </template>
      </section>

      <section v-if="eventCart.items.length" class="co-section co-summary">
        <div class="co-head"><strong>Foto Event</strong></div>
        <div v-for="grp in eventCart.items" :key="'ck' + grp.eventId"><span>{{ grp.eventName }} ({{ grp.qty }} foto)</span><strong>{{ rupiah.format(grp.subtotal) }}</strong></div>
      </section>

      <section class="co-section co-summary">
        <div><span>Subtotal</span><strong>{{ rupiah.format(checkoutSubtotal) }}</strong></div>
        <div v-if="checkoutHasProducts"><span>Ongkir</span><strong>{{ rupiah.format(checkoutRate ? checkoutRate.price : 0) }}</strong></div>
        <div class="co-total"><span>Total</span><strong>{{ rupiah.format(checkoutTotal) }}</strong></div>
      </section>

      <button class="cta" type="button" :disabled="placingOrder || (checkoutHasProducts && !checkoutRate)" @click="placeOrder">{{ placingOrder ? 'Memproses…' : 'Buat pesanan & bayar' }}</button>
    </template>
    <template v-else-if="product">
      <div class="pd">
        <div class="pd-gallery">
          <div class="pd-main">
            <img v-if="heroImage" :src="heroImage" :alt="product.name" @click="zoomUrl = heroImage">
            <span v-else class="pd-fallback">Preview produk</span>
          </div>
          <div v-if="gallery.length > 1" class="pd-thumbs">
            <button v-for="item in gallery" :key="item.key" type="button" class="pd-thumb" :class="{ 'is-active': item.url === heroImage }" @click="manualImageKey = item.key">
              <img :src="item.url" :alt="item.alt || product.name" loading="lazy">
            </button>
          </div>
        </div>

        <div class="pd-info">
          <p class="category">{{ product.category.name }}</p>
          <h1>{{ product.name }}</h1>
          <p class="price">Mulai dari {{ rupiah.format(product.price) }}</p>
          <p v-if="product.shortDescription" class="pd-shortdesc">{{ product.shortDescription }}</p>

          <div v-for="group in level1Groups" :key="group.id" class="pd-field">
            <label class="pd-label">{{ group.name }}</label>
            <select class="pd-select" :value="optionSelection[group.id] || ''" @change="selectOption(group, $event.target.value)">
              <option value="">-</option>
              <option v-for="value in group.values" :key="value.id" :value="value.id">{{ value.name }}<template v-if="value.priceDelta"> (+{{ rupiah.format(value.priceDelta) }})</template></option>
            </select>
          </div>

          <template v-for="group in level1Groups" :key="`sub-${group.id}`">
            <div v-for="sub in dependentGroups(group)" v-show="optionSelection[group.id]" :key="sub.id" class="pd-field">
              <label class="pd-label">{{ sub.name }}</label>
              <select class="pd-select" :value="optionSelection[sub.id] || ''" @change="selectOption(sub, $event.target.value)">
                <option value="">-</option>
                <option v-for="value in dependentValues(sub, group)" :key="value.id" :value="value.id">{{ value.name }}<template v-if="value.priceDelta"> (+{{ rupiah.format(value.priceDelta) }})</template></option>
              </select>
            </div>
          </template>

          <section v-if="product.perluFile" class="pd-field">
            <span class="pd-label">Pengiriman File</span>
            <div class="pd-filebox">
              <label class="pd-radio"><input v-model="fileMethod" type="radio" value="1"> Upload di sini</label>
              <div v-if="fileMethod === '1'" class="pd-fileupload">
                <div class="upload-drop" :class="{ 'is-drag': dragOverFiles }" role="button" tabindex="0"
                  @click="pickFiles" @keydown.enter.prevent="pickFiles" @keydown.space.prevent="pickFiles"
                  @dragover.prevent="dragOverFiles = true" @dragleave.prevent="dragOverFiles = false" @drop.prevent="onDropFiles">
                  <svg class="upload-drop__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 16V4"/><path d="m7 9 5-5 5 5"/><path d="M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
                  <p class="upload-drop__title">Tarik &amp; lepas file di sini</p>
                  <p class="upload-drop__sub">atau klik untuk memilih</p>
                  <p class="upload-drop__limit">JPG, PNG, PDF, ZIP, RAR · maksimal 50 MB per file</p>
                  <input ref="fileInputRef" type="file" multiple hidden accept="image/jpeg,image/png,application/pdf,application/zip,application/vnd.rar,application/x-rar-compressed" @change="onFilesChange">
                </div>
                <p v-if="uploadError" class="upload-error">{{ uploadError }}</p>
                <ul v-if="uploadFiles.length" class="upload-list">
                  <li v-for="(file, index) in uploadFiles" :key="index" class="upload-list__item">
                    <span class="upload-list__ext">{{ fileExt(file.name).toUpperCase().slice(0, 4) }}</span>
                    <span class="upload-list__meta">
                      <span class="upload-list__name" :title="file.name">{{ file.name }}</span>
                      <span class="upload-list__size">{{ sizeLabel(file.size) }}</span>
                    </span>
                    <button type="button" class="upload-list__x" aria-label="Hapus" @click="removeUpload(index)">×</button>
                  </li>
                </ul>
              </div>
              <label class="pd-radio"><input v-model="fileMethod" type="radio" value="2"> Share File, Link Drive</label>
              <input v-if="fileMethod === '2'" v-model="linkDrive" class="pd-input" type="text" placeholder="Tempel link Drive">
            </div>
          </section>

          <section v-if="product.mal.length" class="pd-field">
            <span class="pd-label">Download Mal / Template</span>
            <div class="pd-mal">
              <a v-for="item in product.mal" :key="item.name" :href="item.url" target="_blank" rel="noopener" download>{{ item.name }}</a>
            </div>
          </section>

          <label class="pd-field">
            <span class="pd-label">Catatan</span>
            <textarea v-model="noteText" class="pd-input" rows="3" placeholder="Catatan untuk pesanan"></textarea>
          </label>

          <div class="pd-qtyrow">
            <div class="pd-qty">
              <button type="button" aria-label="Kurangi jumlah" @click="changeQty(-1)">−</button>
              <input v-model.number="qty" type="number" min="1">
              <button type="button" aria-label="Tambah jumlah" @click="changeQty(1)">+</button>
            </div>
            <div class="pd-total"><span>Total Harga</span><strong>{{ rupiah.format(orderTotal) }}</strong></div>
          </div>

          <button class="cta product-add" type="button" :disabled="addingToCart || uploading" @click="addCart">{{ addingToCart || uploading ? 'Memproses…' : '(+) Tambah ke Keranjang' }}</button>
        </div>
      </div>

      <div v-if="product.tabs.length" class="pd-tabs">
        <div class="pd-tabnav">
          <button v-for="(tab, index) in product.tabs" :key="index" type="button" :class="{ 'is-active': activeTab === index }" @click="activeTab = index">{{ tab.title }}</button>
        </div>
        <div class="pd-tabbody" v-html="product.tabs[activeTab]?.html || ''"></div>
      </div>

      <section v-if="product.description && !hasTabs" class="pd-desc">
        <h2>Deskripsi</h2>
        <p class="pd-desc__body">{{ product.description }}</p>
      </section>

      <div class="pd-bottom">
      <div class="pd-sticky">
        <div class="pd-qty">
          <button type="button" aria-label="Kurangi jumlah" @click="changeQty(-1)">−</button>
          <input v-model.number="qty" type="number" min="1">
          <button type="button" aria-label="Tambah jumlah" @click="changeQty(1)">+</button>
        </div>
        <div class="pd-total"><span>Total Harga</span><strong>{{ rupiah.format(orderTotal) }}</strong></div>
      </div>

      <div class="pd-mobilebar">
        <button class="pd-mobilebar__item" type="button" @click="goHome">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V20a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V9.5"/></svg>
          <span>Home</span>
        </button>
        <a class="pd-mobilebar__item" :href="waLink" target="_blank" rel="noopener">
          <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"/></svg>
          <span>Tanya Produk</span>
        </a>
        <button class="pd-mobilebar__item pd-mobilebar__item--cart" type="button" :disabled="addingToCart || uploading" @click="addCart">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M2 3h2.2l2.4 12.2a2 2 0 0 0 2 1.6h8.9a2 2 0 0 0 2-1.6L21 7H5"/><path d="M20 2.5v5M17.5 5h5"/></svg>
          <span>{{ addingToCart || uploading ? '…' : '+ Keranjang' }}</span>
        </button>
      </div>
      </div>

      <div v-if="zoomUrl" class="pd-zoom" @click="zoomUrl = null">
        <img :src="zoomUrl" :alt="product.name">
      </div>
    </template>
    <template v-else>
      <section class="hero">
        <div class="hero-copy">
          <p class="eyebrow">Cetak momen terbaikmu</p>
          <h1>Cerita yang bisa disentuh.</h1>
          <p class="description">Produk personal untuk hadiah, kenangan, dan ruang favorit. Pilih produk, atur desain, kami cetak dan kirim.</p>
        </div>
        <div class="hero-art" aria-hidden="true">
          <span class="hero-card hero-card--a"></span>
          <span class="hero-card hero-card--b"></span>
          <span class="hero-card hero-card--c"></span>
        </div>
      </section>

      <section id="katalog" class="catalog">
        <div class="chips" role="tablist" aria-label="Filter kategori">
          <button class="chip" type="button" :class="{ 'is-active': !activeCategory }" @click="activeCategory = null">Semua</button>
          <button v-for="c in catalog.categories" :key="c.id" class="chip" type="button" :class="{ 'is-active': activeCategory === c.slug }" @click="activeCategory = c.slug">{{ c.name }}</button>
        </div>

        <div class="section-title catalog-title">
          <div>
            <h2>{{ activeCategoryName }}</h2>
            <p class="section-sub">Menampilkan {{ displayProducts.length }} produk</p>
          </div>
          <button v-if="activeCategory" class="ghost ghost--sm" type="button" @click="activeCategory = null">Reset filter</button>
        </div>

        <p v-if="catalogError" class="error">{{ catalogError }}</p>
        <div v-else-if="loading" class="products">
          <div v-for="n in 4" :key="n" class="product-card product-card--skeleton" aria-hidden="true">
            <span class="skeleton skeleton--media"></span>
            <span class="skeleton skeleton--line"></span>
            <span class="skeleton skeleton--line skeleton--short"></span>
          </div>
        </div>
        <p v-else-if="!displayProducts.length" class="empty">Belum ada produk di kategori ini.</p>
        <div v-else class="products">
          <button v-for="item in displayProducts" :key="item.id" class="product-card" type="button" @click="openProduct(item.slug)">
            <span class="product-card__media">
              <img v-if="item.coverImage" :src="item.coverImage" :alt="item.name" loading="lazy">
              <span v-else class="product-card__fallback">{{ item.category.name }}</span>
            </span>
            <span class="product-card__body">
              <span class="product-card__cat">{{ item.category.name }}</span>
              <span class="product-card__name">{{ item.name }}</span>
              <span class="product-card__price">Mulai {{ rupiah.format(item.price) }}</span>
            </span>
          </button>
        </div>
      </section>
    </template>
    </div>
    <div v-show="loginOpen" class="login-overlay" role="dialog" aria-modal="true" aria-label="Masuk dengan Google" @click.self="loginOpen = false">
      <div class="login-card">
        <button class="login-close" type="button" aria-label="Tutup" @click="loginOpen = false">×</button>
        <p class="category">Akun</p>
        <h2>Masuk ke Vita Pictura</h2>
        <p class="description">Pilih akun Google untuk melanjutkan.</p>
        <div ref="googleBtn" class="google-slot" :aria-busy="googleLoading"></div>
        <p v-if="googleLoading" class="muted">Memuat tombol Google…</p>
        <p v-if="googleError" class="error">{{ googleError }}</p>
      </div>
    </div>
    <div v-show="uploading" class="upload-overlay" role="alertdialog" aria-modal="true" aria-busy="true" aria-label="Mengunggah berkas">
      <div class="upload-card">
        <div class="upload-spinner" aria-hidden="true"></div>
        <h3>Mengunggah berkas…</h3>
        <p class="upload-meta">{{ uploadIndex }} dari {{ uploadTotal }} berkas</p>
        <div class="upload-bar"><span :style="{ width: uploadPercent + '%' }"></span></div>
        <strong class="upload-percent">{{ uploadPercent }}%</strong>
        <p class="upload-note">Mohon jangan tutup halaman ini.</p>
      </div>
    </div>
    <nav v-if="!product" class="bottom-nav" aria-label="Navigasi bawah">
      <button class="bottom-nav__item" type="button" :class="{ 'is-active': !product && !cartOpen && !ordersOpen && !addressBook && !eventsOpen && !eventDetail }" :aria-current="(!product && !cartOpen && !ordersOpen && !addressBook && !eventsOpen && !eventDetail) ? 'page' : null" @click="goHome">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V20a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V9.5"/></svg>
        <span>Home</span>
      </button>
      <button class="bottom-nav__item" type="button" :class="{ 'is-active': eventsOpen || eventDetail }" :aria-current="(eventsOpen || eventDetail) ? 'page' : null" @click="openEvents">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M3 9h18"/><path d="M8 2v4M16 2v4"/></svg>
        <span>Events</span>
      </button>
      <button class="bottom-nav__item" type="button" :class="{ 'is-active': cartOpen }" :aria-current="cartOpen ? 'page' : null" @click="openCart">
        <span class="nav-icon-wrap">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M2 3h2.2l2.4 12.2a2 2 0 0 0 2 1.6h8.9a2 2 0 0 0 2-1.6L21 7H5"/></svg>
          <span v-if="cartCount" class="badge">{{ cartCount }}</span>
        </span>
        <span>Cart</span>
      </button>
      <button class="bottom-nav__item" type="button" :class="{ 'is-active': ordersOpen }" :aria-current="ordersOpen ? 'page' : null" @click="openOrders">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m21 8-9-5-9 5 9 5 9-5Z"/><path d="M3 8v8l9 5 9-5V8"/><path d="M12 13v8"/></svg>
        <span>Orders</span>
      </button>
      <button class="bottom-nav__item" type="button" :class="{ 'is-active': addressBook }" :aria-current="addressBook ? 'page' : null" @click="customer ? openAddresses() : startGoogleLogin()">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="3.5"/><path d="M5 20a7 7 0 0 1 14 0"/></svg>
        <span>Account</span>
      </button>
    </nav>
  </main>
</template>
