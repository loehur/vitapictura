const API_BASE = '/api/Admin/Media'

async function parseResponse(response) {
  const payload = await response.json().catch(() => null)
  if (!response.ok || !payload?.status) {
    throw new Error(payload?.message || 'Request media gagal diproses.')
  }
  return payload.data
}

function request(path, options = {}) {
  return fetch(`${API_BASE}${path}`, {
    credentials: 'include',
    ...options,
    headers: {
      Accept: 'application/json',
      ...(options.headers || {}),
    },
  })
}

export async function browseMedia(folderId = 0) {
  return parseResponse(await request(`/browse?folder_id=${folderId}`, { method: 'GET' }))
}

export async function createFolder(name, parent = 0) {
  return parseResponse(await request('/create_folder', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ name, parent }),
  }))
}

export async function renameFolder(id, name) {
  return parseResponse(await request('/rename_folder', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id, name }),
  }))
}

export async function deleteFolder(id) {
  return parseResponse(await request('/delete_folder', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id }),
  }))
}

export async function uploadMedia(files, folderId = 0) {
  const form = new FormData()
  form.append('folder_id', String(folderId))
  const list = Array.from(files || [])
  if (list.length === 1) {
    form.append('file', list[0])
  } else {
    list.forEach((file) => form.append('files[]', file))
  }
  return parseResponse(await request('/upload', { method: 'POST', body: form }))
}

/**
 * Unggah satu file dengan progress (0..1). Memakai XHR agar progres byte terbaca.
 */
export function uploadMediaWithProgress(file, folderId = 0, onProgress) {
  return new Promise((resolve, reject) => {
    const form = new FormData()
    form.append('folder_id', String(folderId))
    form.append('file', file)

    const xhr = new XMLHttpRequest()
    xhr.open('POST', `${API_BASE}/upload`)
    xhr.withCredentials = true
    xhr.setRequestHeader('Accept', 'application/json')

    if (xhr.upload && typeof onProgress === 'function') {
      xhr.upload.onprogress = (e) => {
        if (e.lengthComputable) onProgress(e.loaded / e.total)
      }
    }

    xhr.onload = () => {
      let payload = null
      try { payload = JSON.parse(xhr.responseText) } catch (e) { payload = null }
      if (xhr.status >= 200 && xhr.status < 300 && payload?.status) {
        resolve(payload.data)
      } else {
        reject(new Error(payload?.message || `Upload gagal (${xhr.status})`))
      }
    }
    xhr.onerror = () => reject(new Error('Upload gagal — koneksi terputus'))
    xhr.send(form)
  })
}

export async function renameFile(id, name) {
  return parseResponse(await request('/rename_file', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id, name }),
  }))
}

export async function deleteFile(id) {
  return parseResponse(await request('/delete_file', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id }),
  }))
}
