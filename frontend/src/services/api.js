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

export function configureApi({ getToken, onUnauthorized }) {
  if (typeof getToken === 'function') {
    authTokenGetter = getToken
  }
  if (typeof onUnauthorized === 'function') {
    unauthorizedHandler = onUnauthorized
  }
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
  (error) => {
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
