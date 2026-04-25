import { defineStore } from 'pinia'
import * as storiesService from '@/services/stories.service'
import { normalizeUser } from '@/stores/profileUtils'

function normalizeStory(raw) {
  if (!raw) return null
  return {
    id: raw.id,
    imageUrl: raw.image_url ?? raw.imageUrl ?? '',
    isVideo: Boolean(raw.is_video ?? raw.isVideo ?? false),
    createdAt: raw.created_at ?? raw.createdAt ?? null,
    expiresAt: raw.expires_at ?? raw.expiresAt ?? null,
    seenByMe: Boolean(raw.seen_by_me ?? raw.seenByMe ?? false),
  }
}

function normalizeGroup(raw) {
  if (!raw || !raw.user) return null
  return {
    user: normalizeUser(raw.user),
    stories: (raw.stories ?? []).map(normalizeStory).filter(Boolean),
    hasUnseen: Boolean(raw.has_unseen),
  }
}

export { normalizeStory, normalizeGroup }

export const useStoriesStore = defineStore('stories', {
  state: () => ({
    groups: [],
    loaded: false,
    loading: false,
    viewerOpen: false,
    viewerGroupIdx: 0,
    viewerStoryIdx: 0,
  }),

  getters: {
    currentGroup: (state) => state.groups[state.viewerGroupIdx] ?? null,
    currentStory: (state) => {
      const group = state.groups[state.viewerGroupIdx]
      return group?.stories[state.viewerStoryIdx] ?? null
    },
    prevGroup: (state) => state.groups[state.viewerGroupIdx - 1] ?? null,
    nextGroup: (state) => state.groups[state.viewerGroupIdx + 1] ?? null,
    hasPrevStory: (state) => {
      return state.viewerStoryIdx > 0 || state.viewerGroupIdx > 0
    },
    hasNextStory: (state) => {
      const group = state.groups[state.viewerGroupIdx]
      if (!group) return false
      return (
        state.viewerStoryIdx < group.stories.length - 1 ||
        state.viewerGroupIdx < state.groups.length - 1
      )
    },
  },

  actions: {
    async fetchFeed() {
      if (this.loading) return
      this.loading = true
      try {
        const res = await storiesService.feedStories()
        this.groups = (res.data ?? []).map(normalizeGroup).filter(Boolean)
        this.loaded = true
      } finally {
        this.loading = false
      }
    },

    openViewer(groupIdx, storyIdx = 0) {
      this.viewerGroupIdx = groupIdx
      this.viewerStoryIdx = storyIdx
      this.viewerOpen = true
    },

    closeViewer() {
      this.viewerOpen = false
    },

    goTo(groupIdx, storyIdx) {
      this.viewerGroupIdx = groupIdx
      this.viewerStoryIdx = storyIdx
    },

    goNext() {
      const group = this.groups[this.viewerGroupIdx]
      if (!group) return false

      if (this.viewerStoryIdx < group.stories.length - 1) {
        this.viewerStoryIdx++
        return true
      }

      if (this.viewerGroupIdx < this.groups.length - 1) {
        this.viewerGroupIdx++
        this.viewerStoryIdx = 0
        return true
      }

      this.closeViewer()
      return false
    },

    goPrev() {
      if (this.viewerStoryIdx > 0) {
        this.viewerStoryIdx--
        return true
      }

      if (this.viewerGroupIdx > 0) {
        this.viewerGroupIdx--
        const group = this.groups[this.viewerGroupIdx]
        this.viewerStoryIdx = group ? group.stories.length - 1 : 0
        return true
      }

      return false
    },

    async markSeen(storyId) {
      try {
        await storiesService.markSeen(storyId)
      } catch {
        // silent — don't break UX if tracking fails
      }
      for (const group of this.groups) {
        const story = group.stories.find((s) => s.id === storyId)
        if (story) {
          story.seenByMe = true
          group.hasUnseen = group.stories.some((s) => !s.seenByMe)
          break
        }
      }
    },

    async createStory(image, currentUserId) {
      const raw = await storiesService.createStory(image)
      const story = normalizeStory(raw)
      if (!story) return

      const ownGroupIdx = this.groups.findIndex((g) => g.user.id === currentUserId)
      if (ownGroupIdx >= 0) {
        this.groups[ownGroupIdx].stories.push(story)
        this.groups[ownGroupIdx].hasUnseen = true
      } else {
        await this.fetchFeed()
      }
    },

    async deleteStory(storyId) {
      await storiesService.destroy(storyId)
      for (const group of this.groups) {
        const idx = group.stories.findIndex((s) => s.id === storyId)
        if (idx >= 0) {
          group.stories.splice(idx, 1)
          group.hasUnseen = group.stories.some((s) => !s.seenByMe)
          break
        }
      }
      this.groups = this.groups.filter((g) => g.stories.length > 0)
    },
  },
})
