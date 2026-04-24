import { api } from '@/services/api'

export async function like(postId) {
  const { data } = await api.post(`/posts/${postId}/like`)
  return data
}

export async function unlike(postId) {
  const { data } = await api.delete(`/posts/${postId}/unlike`)
  return data
}

export async function likers(postId, perPage = 20, page = 1) {
  const { data } = await api.get(`/posts/${postId}/likes`, {
    params: { per_page: perPage, page },
  })
  return data
}
