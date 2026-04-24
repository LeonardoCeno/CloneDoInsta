import { storeToRefs } from 'pinia'
import { useAuthStore } from '@/stores/auth'

export const PROFILE_NAME_MAX_LENGTH = 255
export const PROFILE_USERNAME_MAX_LENGTH = 30
export const PROFILE_BIO_MAX_LENGTH = 500

export function useAuth() {
  const authStore = useAuthStore()
  const { currentUser, isAuthenticated, token } = storeToRefs(authStore)

  return {
    token,
    currentUser,
    isAuthenticated,
    login: authStore.login,
    register: authStore.register,
    logout: authStore.logout,
    updateProfile: authStore.updateProfile,
    uploadAvatar: authStore.uploadAvatar,
    hydrateAuthState: authStore.hydrateAuthState,
  }
}
