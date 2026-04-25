import { api } from '@/services/api'

export async function save(postId) {
  const { data } = await api.post(`/posts/${postId}/save`)
  return data
}

export async function unsave(postId) {
  const { data } = await api.delete(`/posts/${postId}/save`)
  return data
}

export async function mySaved(perPage = 15, page = 1) {
  const { data } = await api.get('/users/me/saved', {
    params: { per_page: perPage, page },
  })
  return data
}
