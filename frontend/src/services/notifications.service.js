import { api } from '@/services/api'

export async function list(perPage = 20, page = 1) {
  const { data } = await api.get('/notifications', {
    params: { per_page: perPage, page },
  })
  return data
}

export async function unreadCount() {
  const { data } = await api.get('/notifications/unread-count')
  return data
}

export async function markRead() {
  const { data } = await api.put('/notifications/read')
  return data
}
