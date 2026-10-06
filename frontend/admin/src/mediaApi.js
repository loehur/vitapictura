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
