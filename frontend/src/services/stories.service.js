import { api } from '@/services/api'

export async function feedStories() {
  const { data } = await api.get('/stories/feed')
  return data
}

export async function createStory(image) {
  const form = new FormData()
  form.append('image', image)
  const { data } = await api.post('/stories', form, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  return data
}

export async function markSeen(storyId) {
  const { data } = await api.post(`/stories/${storyId}/seen`)
  return data
}

export async function destroy(storyId) {
  const { data } = await api.delete(`/stories/${storyId}`)
  return data
}
