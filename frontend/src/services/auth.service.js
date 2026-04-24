import { api } from '@/services/api'

export async function login(credentials) {
  const { data } = await api.post('/auth/login', credentials)
  return data
}

export async function register(payload) {
  const { data } = await api.post('/auth/register', payload)
  return data
}

export async function logout() {
  const { data } = await api.post('/auth/logout')
  return data
}

export async function refresh() {
  const { data } = await api.post('/auth/refresh')
  return data
}

export async function me() {
  const { data } = await api.get('/auth/me')
  return data
}
