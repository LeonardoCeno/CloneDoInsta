import { api } from '@/services/api'

export async function repost(postId) {
  const { data } = await api.post(`/posts/${postId}/repost`)
  return data
}

export async function unrepost(postId) {
  const { data } = await api.delete(`/posts/${postId}/repost`)
  return data
}

export async function getUserReposts(userId, perPage = 15, page = 1) {
  const { data } = await api.get(`/users/${userId}/reposts`, {
    params: { per_page: perPage, page },
  })
  return data
}
