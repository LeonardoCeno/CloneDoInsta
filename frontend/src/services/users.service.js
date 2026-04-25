import { api } from '@/services/api'

export async function getByUsername(username) {
  const { data } = await api.get(`/users/${encodeURIComponent(username)}`)
  return data
}

export async function updateMe(payload) {
  const { data } = await api.put('/users/me', payload)
  return data
}

export async function uploadAvatar(file) {
  const formData = new FormData()
  formData.append('avatar', file)
  const { data } = await api.post('/users/me/avatar', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  return data
}

export async function search(query, perPage = 15) {
  const { data } = await api.get('/users/search', {
    params: { q: query, per_page: perPage },
  })
  return data
}

export async function suggestions(perPage = 20, page = 1) {
  const { data } = await api.get('/users/suggestions', {
    params: { per_page: perPage, page },
  })
  return data
}

export async function getPostsByUser(userId, perPage = 15, page = 1) {
  const { data } = await api.get(`/users/${userId}/posts`, {
    params: { per_page: perPage, page },
  })
  return data
}

export async function getRepostsByUser(userId, perPage = 15, page = 1) {
  const { data } = await api.get(`/users/${userId}/reposts`, {
    params: { per_page: perPage, page },
  })
  return data
}

export async function togglePrivacy() {
  const { data } = await api.put('/users/me/privacy')
  return data
}

export async function deleteAccount() {
  const { data } = await api.delete('/users/me')
  return data
}
