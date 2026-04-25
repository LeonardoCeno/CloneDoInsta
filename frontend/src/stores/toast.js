import { defineStore } from 'pinia'

let nextId = 1

export const useToastStore = defineStore('toast', {
  state: () => ({
    toasts: [],
  }),
  actions: {
    show(message, type = 'info', duration = 3500) {
      const id = nextId++
      this.toasts.push({ id, message, type })
      setTimeout(() => this.dismiss(id), duration)
    },
    dismiss(id) {
      const idx = this.toasts.findIndex((t) => t.id === id)
      if (idx >= 0) {
        this.toasts.splice(idx, 1)
      }
    },
  },
})
