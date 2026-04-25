import { defineStore } from 'pinia'
import * as notificationsService from '@/services/notifications.service'

function normalizeNotification(raw) {
  if (!raw) return null
  return {
    id: raw.id,
    type: raw.type,
    data: raw.data ?? {},
    readAt: raw.read_at ?? null,
    createdAt: raw.created_at ?? null,
  }
}

export const useNotificationsStore = defineStore('notifications', {
  state: () => ({
    notifications: [],
    unreadCount: 0,
    currentPage: 1,
    hasMore: false,
    loaded: false,
    loading: false,
  }),

  actions: {
    async fetchUnreadCount() {
      try {
        const res = await notificationsService.unreadCount()
        this.unreadCount = Number(res.count ?? 0)
      } catch {
        // silent — polling failure shouldn't break the UI
      }
    },

    async fetchList({ reset = true } = {}) {
      if (this.loading) return
      this.loading = true

      try {
        const page = reset ? 1 : this.currentPage + 1
        const res = await notificationsService.list(20, page)
        const normalized = (res.data ?? []).map(normalizeNotification).filter(Boolean)

        this.notifications = reset ? normalized : [...this.notifications, ...normalized]
        this.currentPage = Number(res.current_page ?? page)
        this.hasMore = Boolean(res.next_page_url)
        this.loaded = true
      } finally {
        this.loading = false
      }
    },

    async markAllRead() {
      await notificationsService.markRead()
      this.unreadCount = 0
      this.notifications = this.notifications.map((n) => ({
        ...n,
        readAt: n.readAt ?? new Date().toISOString(),
      }))
    },
  },
})
