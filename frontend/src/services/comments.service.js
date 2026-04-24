import { api } from '@/services/api'

export async function listByPost(postId, perPage = 10, page = 1) {
  const { data } = await api.get(`/posts/${postId}/comments`, {
    params: { per_page: perPage, page },
  })
  return data
}

export async function create(postId, body) {
  const { data } = await api.post(`/posts/${postId}/comments`, { body })
  return data
}

export async function update(commentId, body) {
  const { data } = await api.put(`/comments/${commentId}`, { body })
  return data
}

export async function destroy(commentId) {
  const { data } = await api.delete(`/comments/${commentId}`)
  return data
}
