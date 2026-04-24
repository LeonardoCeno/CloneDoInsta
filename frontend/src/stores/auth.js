import { defineStore } from 'pinia'
import * as authService from '@/services/auth.service'
import * as usersService from '@/services/users.service'
import { normalizeUser } from '@/stores/profileUtils'

const TOKEN_STORAGE_KEY = 'instaclone.token'

function canUseStorage() {
  return typeof window !== 'undefined' && Boolean(window.localStorage)
}

function readStoredToken() {
  if (!canUseStorage()) {
    return ''
  }
  return localStorage.getItem(TOKEN_STORAGE_KEY) || ''
}

function writeStoredToken(token) {
  if (!canUseStorage()) {
    return
  }
  if (token) {
    localStorage.setItem(TOKEN_STORAGE_KEY, token)
  } else {
    localStorage.removeItem(TOKEN_STORAGE_KEY)
  }
}

export const useAuthStore = defineStore('auth', {
  state: () => ({
    token: '',
    user: null,
    hydrated: false,
    hydrating: false,
  }),
  getters: {
    isAuthenticated: (state) => Boolean(state.token && state.user),
    currentUser: (state) => state.user,
  },
  actions: {
    async hydrateAuthState() {
      if (this.hydrated || this.hydrating) {
        return
      }

      this.hydrating = true
      const storedToken = readStoredToken()

      if (!storedToken) {
        this.token = ''
        this.user = null
        this.hydrated = true
        this.hydrating = false
        return
      }

      this.token = storedToken

      try {
        const payload = await authService.me()
        this.user = normalizeUser(payload)
      } catch {
        this.clearSession()
      } finally {
        this.hydrated = true
        this.hydrating = false
      }
    },

    async login(credentials) {
      const response = await authService.login(credentials)
      this.setSession(response.access_token, response.user)
      return this.user
    },

    async register(payload) {
      const response = await authService.register(payload)
      this.setSession(response.access_token, response.user)
      return this.user
    },

    async updateProfile(payload) {
      const user = await usersService.updateMe(payload)
      this.user = normalizeUser(user)
      return this.user
    },

    async uploadAvatar(file) {
      const user = await usersService.uploadAvatar(file)
      this.user = normalizeUser(user)
      return this.user
    },

    async logout() {
      try {
        await authService.logout()
      } catch {
        // token may already be invalid; ignore
      }
      this.clearSession()
    },

    setSession(token, user) {
      this.token = token
      this.user = normalizeUser(user)
      this.hydrated = true
      writeStoredToken(token)
    },

    clearSession() {
      this.token = ''
      this.user = null
      this.hydrated = true
      writeStoredToken('')
    },
  },
})
