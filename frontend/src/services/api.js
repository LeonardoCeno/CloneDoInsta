import axios from 'axios'

const baseURL = import.meta.env.VITE_API_URL || 'http://localhost:8000/api'

export const api = axios.create({
  baseURL,
  headers: {
    Accept: 'application/json',
  },
})

let authTokenGetter = () => ''
let unauthorizedHandler = () => {}
let tokenRefresher = null

let pendingRefresh = null

export function configureApi({ getToken, onUnauthorized, refreshToken }) {
  if (typeof getToken === 'function') authTokenGetter = getToken
  if (typeof onUnauthorized === 'function') unauthorizedHandler = onUnauthorized
  if (typeof refreshToken === 'function') tokenRefresher = refreshToken
}

api.interceptors.request.use((config) => {
  const token = authTokenGetter()
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

api.interceptors.response.use(
  (response) => response,
  async (error) => {
    const original = error.config

    // Only attempt refresh on 401, if we have a refresher, and haven't retried yet
    if (error.response?.status === 401 && tokenRefresher && !original._retry) {
      original._retry = true

      try {
        // Singleton: if a refresh is already in flight, wait for it instead of firing another
        if (!pendingRefresh) {
          pendingRefresh = tokenRefresher().finally(() => {
            pendingRefresh = null
          })
        }

        const newToken = await pendingRefresh
        original.headers.Authorization = `Bearer ${newToken}`
        return api(original)
      } catch {
        unauthorizedHandler()
        return Promise.reject(error)
      }
    }

    if (error.response?.status === 401) {
      unauthorizedHandler()
    }

    return Promise.reject(error)
  },
)

export function extractErrorMessage(error, fallback = 'Não foi possível concluir a operação.') {
  const data = error?.response?.data
  if (!data) {
    return error?.message || fallback
  }
  if (data.errors && typeof data.errors === 'object') {
    const firstKey = Object.keys(data.errors)[0]
    const firstMessages = data.errors[firstKey]
    if (Array.isArray(firstMessages) && firstMessages.length > 0) {
      return firstMessages[0]
    }
  }
  if (typeof data.message === 'string' && data.message.length > 0) {
    return data.message
  }
  return fallback
}
