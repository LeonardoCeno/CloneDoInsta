import { api } from '@/services/api'

export async function getFeed({ cursor = null, perPage = 10 } = {}) {
  const params = { per_page: perPage }
  if (cursor) {
    params.cursor = cursor
  }
  const { data } = await api.get('/feed', { params })
  return data
}
