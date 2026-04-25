import { api } from '@/services/api'

export async function create({ image, caption }) {
  const formData = new FormData()
  formData.append('image', image)
  if (caption !== undefined && caption !== null) {
    formData.append('caption', caption)
  }
  const { data } = await api.post('/posts', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  return data
}

export async function show(postId) {
  const { data } = await api.get(`/posts/${postId}`)
  return data
}

export async function update(postId, payload) {
  const { data } = await api.put(`/posts/${postId}`, payload)
  return data
}

export async function destroy(postId) {
  const { data } = await api.delete(`/posts/${postId}`)
  return data
}

export async function explore(perPage = 18, page = 1) {
  const { data } = await api.get('/posts/explore', {
    params: { per_page: perPage, page },
  })
  return data
}
