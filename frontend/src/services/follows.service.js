import { ref } from 'vue'
import { api } from '@/services/api'

export const lastFollowAt = ref(0)

export async function follow(userId) {
  const { data } = await api.post(`/users/${userId}/follow`)
  lastFollowAt.value = Date.now()
  return data
}

export async function unfollow(userId) {
  const { data } = await api.delete(`/users/${userId}/follow`)
  return data
}

export async function followers(userId, perPage = 20, page = 1) {
  const { data } = await api.get(`/users/${userId}/followers`, {
    params: { per_page: perPage, page },
  })
  return data
}

export async function following(userId, perPage = 20, page = 1) {
  const { data } = await api.get(`/users/${userId}/following`, {
    params: { per_page: perPage, page },
  })
  return data
}

export async function isFollowing(userId) {
  const { data } = await api.get(`/users/${userId}/is-following`)
  return data
}

export async function removeFollower(userId) {
  const { data } = await api.delete(`/users/${userId}/followers`)
  return data
}

export async function acceptRequest(userId) {
  const { data } = await api.post(`/users/${userId}/follow/accept`)
  return data
}

export async function declineRequest(userId) {
  const { data } = await api.post(`/users/${userId}/follow/decline`)
  return data
}
