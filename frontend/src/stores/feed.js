import { defineStore } from 'pinia'
import * as feedService from '@/services/feed.service'
import * as postsService from '@/services/posts.service'
import * as likesService from '@/services/likes.service'
import * as savesService from '@/services/saves.service'
import * as commentsService from '@/services/comments.service'
import { normalizeUser } from '@/stores/profileUtils'

export const POST_CAPTION_MAX_LENGTH = 2200

export function normalizePost(rawPost) {
  if (!rawPost || typeof rawPost !== 'object') {
    return null
  }

  const author = normalizeUser(rawPost.user) || {
    id: rawPost.user_id ?? null,
    name: 'Usuário',
    username: 'usuario',
    email: '',
    bio: '',
    avatarUrl: '',
    colors: ['#f05a28', '#ff9f59'],
  }

  const caption = rawPost.caption ?? ''

  return {
    id: rawPost.id,
    author,
    caption,
    imageUrl: rawPost.image_url ?? rawPost.imageUrl ?? '',
    imageAlt: caption
      ? `Publicação de @${author.username}: ${caption.slice(0, 96)}`
      : `Publicação de @${author.username}.`,
    likesCount: Number(rawPost.likes_count ?? rawPost.likesCount ?? 0),
    commentsCount: Number(rawPost.comments_count ?? rawPost.commentsCount ?? 0),
    likedByMe: Boolean(rawPost.liked_by_me ?? rawPost.likedByMe ?? false),
    savedByMe: Boolean(rawPost.saved_by_me ?? rawPost.savedByMe ?? false),
    createdAt: rawPost.created_at ?? rawPost.createdAt ?? null,
    updatedAt: rawPost.updated_at ?? rawPost.updatedAt ?? null,
  }
}

export const useFeedStore = defineStore('feed', {
  state: () => ({
    feedPosts: [],
    feedCursor: null,
    feedHasNext: false,
    feedLoaded: false,
    feedLoading: false,
    savedPosts: [],
    savedPage: 1,
    savedHasNext: false,
    savedLoaded: false,
    savedLoading: false,
  }),
  actions: {
    async fetchFeed({ reset = false } = {}) {
      if (this.feedLoading) {
        return
      }

      this.feedLoading = true

      try {
        const cursor = reset ? null : this.feedCursor
        const response = await feedService.getFeed({ cursor, perPage: 10 })

        const normalized = (response.data ?? []).map(normalizePost).filter(Boolean)

        if (reset) {
          this.feedPosts = normalized
        } else {
          this.feedPosts = [...this.feedPosts, ...normalized]
        }

        this.feedCursor = response.next_cursor ?? null
        this.feedHasNext = Boolean(response.next_cursor)
        this.feedLoaded = true
      } finally {
        this.feedLoading = false
      }
    },

    async loadMoreFeed() {
      if (!this.feedHasNext) {
        return
      }
      await this.fetchFeed({ reset: false })
    },

    async createPost({ image, caption }) {
      const created = await postsService.create({ image, caption })
      const normalized = normalizePost(created)
      if (normalized) {
        this.feedPosts = [normalized, ...this.feedPosts]
      }
      return normalized
    },

    async deletePost(postId) {
      await postsService.destroy(postId)
      this.feedPosts = this.feedPosts.filter((post) => post.id !== postId)
    },

    async toggleLike(post) {
      if (!post) {
        return null
      }

      const action = post.likedByMe ? likesService.unlike : likesService.like
      const response = await action(post.id)
      this.applyPostPatch(post.id, {
        likedByMe: Boolean(response.liked),
        likesCount: Number(response.likes_count ?? post.likesCount),
      })
      return response
    },

    async toggleSave(post) {
      if (!post) {
        return null
      }

      const action = post.savedByMe ? savesService.unsave : savesService.save
      const response = await action(post.id)
      const savedByMe = Boolean(response.saved)
      this.applyPostPatch(post.id, { savedByMe })
      const idx = this.savedPosts.findIndex((p) => p.id === post.id)
      if (!savedByMe && idx >= 0) {
        this.savedPosts.splice(idx, 1)
      }
      return response
    },

    async fetchSaved({ reset = false } = {}) {
      if (this.savedLoading) {
        return
      }

      this.savedLoading = true

      try {
        const page = reset ? 1 : this.savedPage + 1
        const response = await savesService.mySaved(15, page)
        const normalized = (response.data ?? []).map(normalizePost).filter(Boolean)

        if (reset) {
          this.savedPosts = normalized
        } else {
          this.savedPosts = [...this.savedPosts, ...normalized]
        }

        this.savedPage = Number(response.current_page ?? page)
        this.savedHasNext = Boolean(response.next_page_url)
        this.savedLoaded = true
      } finally {
        this.savedLoading = false
      }
    },

    async addComment(postId, body) {
      const comment = await commentsService.create(postId, body)
      this.applyPostPatch(postId, (post) => ({
        commentsCount: post.commentsCount + 1,
      }))
      return comment
    },

    applyPostPatch(postId, patch) {
      const index = this.feedPosts.findIndex((post) => post.id === postId)
      if (index < 0) {
        return
      }

      const current = this.feedPosts[index]
      const nextPatch = typeof patch === 'function' ? patch(current) : patch
      this.feedPosts[index] = { ...current, ...nextPatch }
    },
  },
})
