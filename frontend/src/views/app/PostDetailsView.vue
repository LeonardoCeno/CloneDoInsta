<script setup>
import { computed, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import ProfileAvatar from '@/components/profile/ProfileAvatar.vue'
import AppIcon from '@/components/layout/AppIcon.vue'
import MediaDisplay from '@/components/shared/MediaDisplay.vue'
import { useAuth } from '@/composables/useAuth'
import * as postsService from '@/services/posts.service'
import * as likesService from '@/services/likes.service'
import * as savesService from '@/services/saves.service'
import * as commentsService from '@/services/comments.service'
import * as repostsService from '@/services/reposts.service'
import { extractErrorMessage } from '@/services/api'
import { normalizePost, useFeed } from '@/composables/useFeed'
import { normalizeUser } from '@/stores/profileUtils'
import { useToastStore } from '@/stores/toast'

const COMMENTS_PAGE_SIZE = 20

const route = useRoute()
const router = useRouter()
const { currentUser } = useAuth()
const { applyPostPatch } = useFeed()
const toastStore = useToastStore()

const post = ref(null)
const loadError = ref('')
const isLoading = ref(false)

const comments = ref([])
const commentsCurrentPage = ref(1)
const commentsHasMore = ref(false)
const commentsLoading = ref(false)

const commentText = ref('')
const isSubmittingComment = ref(false)
const likePending = ref(false)
const savePending = ref(false)
const repostPending = ref(false)
const deletePending = ref(false)
const showOwnerMenu = ref(false)

const postId = computed(() =>
  typeof route.params.postId === 'string'
    ? route.params.postId.trim()
    : String(route.params.postId ?? ''),
)

const navIds = computed(() => {
  const raw = route.query.ids
  if (!raw || typeof raw !== 'string') return []
  return raw.split(',').filter(Boolean)
})

const navIdx = computed(() => {
  const n = Number(route.query.idx)
  return isNaN(n) ? -1 : n
})

const hasPrev = computed(() => navIds.value.length > 0 && navIdx.value > 0)
const hasNext = computed(() => navIds.value.length > 0 && navIdx.value < navIds.value.length - 1)

function goToNav(idx) {
  const id = navIds.value[idx]
  if (!id) return
  router.replace({
    name: 'post-detalhes',
    params: { postId: id },
    query: { ids: route.query.ids, idx },
  })
}

const isOwner = computed(
  () => Boolean(currentUser.value?.id && post.value?.author.id === currentUser.value.id),
)

const authorLink = computed(() => {
  if (!post.value) return { name: 'perfil' }
  if (currentUser.value?.username === post.value.author.username) return { name: 'perfil' }
  return { name: 'perfil', query: { user: post.value.author.username } }
})

const trimmedComment = computed(() => commentText.value.trim())

const likesLabel = computed(() => {
  const n = post.value?.likesCount ?? 0
  return `${n.toLocaleString('pt-BR')} ${n === 1 ? 'curtida' : 'curtidas'}`
})

const publishedLabel = computed(() => {
  if (!post.value?.createdAt) return ''
  return new Intl.DateTimeFormat('pt-BR', {
    day: '2-digit', month: 'long', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  }).format(new Date(post.value.createdAt))
})

function normalizeComment(raw) {
  if (!raw || typeof raw !== 'object') return null
  const author = normalizeUser(raw.user) || {
    id: raw.user_id ?? null,
    name: 'Usuário',
    username: 'usuario',
    email: '',
    bio: '',
    avatarUrl: '',
    colors: ['#f05a28', '#ff9f59'],
  }
  return {
    id: raw.id,
    body: raw.body ?? '',
    author,
    authorId: raw.user_id ?? author.id,
    createdAt: raw.created_at ?? raw.createdAt ?? null,
  }
}

function formatCommentDate(value) {
  if (!value) return ''
  const diffMs = Math.max(0, Date.now() - new Date(value).getTime())
  const minute = 60_000
  const hour = 60 * minute
  const day = 24 * hour
  const week = 7 * day
  if (diffMs < hour) return `${Math.max(1, Math.floor(diffMs / minute))} min`
  if (diffMs < day) return `${Math.floor(diffMs / hour)} h`
  if (diffMs < week) return `${Math.floor(diffMs / day)} d`
  return new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: 'short' }).format(new Date(value))
}

async function loadPost() {
  if (!postId.value) return
  isLoading.value = true
  loadError.value = ''
  try {
    const raw = await postsService.show(postId.value)
    post.value = normalizePost(raw)
  } catch (error) {
    post.value = null
    loadError.value = extractErrorMessage(error, 'Post não encontrado.')
  } finally {
    isLoading.value = false
  }
}

async function loadComments({ reset = true } = {}) {
  if (!postId.value) return
  commentsLoading.value = true
  try {
    const page = reset ? 1 : commentsCurrentPage.value + 1
    const response = await commentsService.listByPost(postId.value, COMMENTS_PAGE_SIZE, page)
    const items = (response.data ?? []).map(normalizeComment).filter(Boolean)
    comments.value = reset ? items : [...comments.value, ...items]
    commentsCurrentPage.value = Number(response.current_page ?? page)
    commentsHasMore.value = Boolean(response.next_page_url)
  } finally {
    commentsLoading.value = false
  }
}

async function handleToggleLike() {
  if (!post.value || likePending.value) return
  likePending.value = true
  try {
    const action = post.value.likedByMe ? likesService.unlike : likesService.like
    const response = await action(post.value.id)
    post.value = {
      ...post.value,
      likedByMe: Boolean(response.liked),
      likesCount: Number(response.likes_count ?? post.value.likesCount),
    }
    applyPostPatch(post.value.id, { likedByMe: post.value.likedByMe, likesCount: post.value.likesCount })
  } finally {
    likePending.value = false
  }
}

async function handleToggleRepost() {
  if (!post.value || repostPending.value) return
  repostPending.value = true
  try {
    if (post.value.repostedByMe) {
      await repostsService.unrepost(post.value.id)
      post.value = { ...post.value, repostedByMe: false, repostsCount: Math.max(0, post.value.repostsCount - 1) }
    } else {
      await repostsService.repost(post.value.id)
      post.value = { ...post.value, repostedByMe: true, repostsCount: post.value.repostsCount + 1 }
      toastStore.show('Publicação republicada!', 'success')
    }
    applyPostPatch(post.value.id, { repostedByMe: post.value.repostedByMe, repostsCount: post.value.repostsCount })
  } finally {
    repostPending.value = false
  }
}

async function handleToggleSave() {
  if (!post.value || savePending.value) return
  savePending.value = true
  try {
    const action = post.value.savedByMe ? savesService.unsave : savesService.save
    const response = await action(post.value.id)
    post.value = { ...post.value, savedByMe: Boolean(response.saved) }
    applyPostPatch(post.value.id, { savedByMe: post.value.savedByMe })
  } finally {
    savePending.value = false
  }
}

async function handleCommentSubmit() {
  if (!trimmedComment.value || !post.value || isSubmittingComment.value) return
  isSubmittingComment.value = true
  try {
    const created = await commentsService.create(post.value.id, trimmedComment.value)
    const normalized = normalizeComment(created)
    if (normalized) {
      comments.value = [normalized, ...comments.value]
    }
    post.value = { ...post.value, commentsCount: post.value.commentsCount + 1 }
    applyPostPatch(post.value.id, (c) => ({ commentsCount: c.commentsCount + 1 }))
    commentText.value = ''
  } finally {
    isSubmittingComment.value = false
  }
}

async function handleDeleteComment(comment) {
  if (!comment || comment.authorId !== currentUser.value?.id) return
  if (!window.confirm('Apagar este comentário?')) return
  try {
    await commentsService.destroy(comment.id)
    comments.value = comments.value.filter((c) => c.id !== comment.id)
    if (post.value) {
      post.value = { ...post.value, commentsCount: Math.max(0, post.value.commentsCount - 1) }
      applyPostPatch(post.value.id, (c) => ({ commentsCount: Math.max(0, c.commentsCount - 1) }))
    }
  } catch {
    // silently fail
  }
}

async function handleDeletePost() {
  if (!post.value || !isOwner.value || deletePending.value) return
  if (!window.confirm('Deletar este post permanentemente?')) return
  deletePending.value = true
  showOwnerMenu.value = false
  try {
    await postsService.destroy(post.value.id)
    router.replace({ name: 'perfil' })
  } finally {
    deletePending.value = false
  }
}

function close() {
  router.back()
}

watch(
  postId,
  async () => {
    commentText.value = ''
    comments.value = []
    commentsCurrentPage.value = 1
    commentsHasMore.value = false
    await loadPost()
    if (post.value) await loadComments({ reset: true })
  },
  { immediate: true },
)
</script>

<template>
  <!-- Modal overlay -->
  <Teleport to="body">
    <div class="pm-overlay" role="dialog" aria-modal="true" @click.self="close">

      <!-- Close button -->
      <button class="pm-close" type="button" aria-label="Fechar" @click="close">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M18 6 6 18"/><path d="M6 6l12 12"/>
        </svg>
      </button>

      <!-- Loading -->
      <div v-if="isLoading && !post" class="pm-loading">
        <span>Carregando...</span>
      </div>

      <!-- Error -->
      <div v-else-if="loadError && !post" class="pm-error">
        <p>{{ loadError }}</p>
        <button type="button" @click="close">Fechar</button>
      </div>

      <!-- Prev arrow -->
      <button
        v-if="hasPrev"
        class="pm-nav pm-nav--prev"
        type="button"
        aria-label="Post anterior"
        @click="goToNav(navIdx - 1)"
      >
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
      </button>

      <!-- Modal card -->
      <article v-else-if="post" class="pm-card">

        <!-- Left: media -->
        <div class="pm-media">
          <MediaDisplay
            class="pm-media__el"
            :src="post.imageUrl"
            :alt="post.imageAlt"
            :is-video="post.isVideo"
            :autoplay="post.isVideo"
            :muted="false"
            :controls="true"
            :loop="true"
          />
        </div>

        <!-- Right: side panel -->
        <div class="pm-side">

          <!-- Header -->
          <header class="pm-head">
            <RouterLink :to="authorLink" class="pm-head__author" @click="close">
              <ProfileAvatar
                :name="post.author.name"
                :username="post.author.username"
                :avatar-url="post.author.avatarUrl"
                :colors="post.author.colors"
                size="sm"
              />
              <span class="pm-head__username">{{ post.author.username }}</span>
            </RouterLink>

            <div class="pm-head__end">
              <!-- Owner menu -->
              <div v-if="isOwner" class="pm-owner-wrap">
                <button
                  class="pm-icon-btn"
                  type="button"
                  aria-label="Mais opções"
                  @click="showOwnerMenu = !showOwnerMenu"
                >
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                    <circle cx="5" cy="12" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="19" cy="12" r="1.6"/>
                  </svg>
                </button>
                <div v-if="showOwnerMenu" class="pm-owner-menu">
                  <button
                    class="pm-owner-menu__item pm-owner-menu__item--danger"
                    type="button"
                    :disabled="deletePending"
                    @click="handleDeletePost"
                  >
                    {{ deletePending ? 'Deletando...' : 'Deletar post' }}
                  </button>
                  <button
                    class="pm-owner-menu__item"
                    type="button"
                    @click="showOwnerMenu = false"
                  >
                    Cancelar
                  </button>
                </div>
              </div>
            </div>
          </header>

          <!-- Body: caption + comments -->
          <div class="pm-body">
            <!-- Caption as pinned comment -->
            <div v-if="post.caption" class="pm-comment">
              <ProfileAvatar
                :name="post.author.name"
                :username="post.author.username"
                :avatar-url="post.author.avatarUrl"
                :colors="post.author.colors"
                size="sm"
              />
              <div class="pm-comment__content">
                <p class="pm-comment__text">
                  <RouterLink :to="authorLink" class="pm-comment__name" @click="close">{{ post.author.username }}</RouterLink>
                  {{ post.caption }}
                </p>
              </div>
            </div>

            <!-- Comments -->
            <div
              v-for="comment in comments"
              :key="comment.id"
              class="pm-comment"
            >
              <RouterLink
                :to="{ name: 'perfil', query: { user: comment.author.username } }"
                class="pm-comment__avatar-link"
                @click="close"
              >
                <ProfileAvatar
                  :name="comment.author.name"
                  :username="comment.author.username"
                  :avatar-url="comment.author.avatarUrl"
                  :colors="comment.author.colors"
                  size="sm"
                />
              </RouterLink>
              <div class="pm-comment__content">
                <p class="pm-comment__text">
                  <RouterLink
                    :to="{ name: 'perfil', query: { user: comment.author.username } }"
                    class="pm-comment__name"
                    @click="close"
                  >{{ comment.author.username }}</RouterLink>
                  {{ comment.body }}
                </p>
                <div class="pm-comment__meta">
                  <span>{{ formatCommentDate(comment.createdAt) }}</span>
                  <button
                    v-if="currentUser?.id === comment.authorId"
                    class="pm-comment__delete"
                    type="button"
                    @click="handleDeleteComment(comment)"
                  >
                    Apagar
                  </button>
                </div>
              </div>
            </div>

            <!-- Load more comments -->
            <button
              v-if="commentsHasMore"
              class="pm-load-more"
              type="button"
              :disabled="commentsLoading"
              @click="loadComments({ reset: false })"
            >
              {{ commentsLoading ? 'Carregando...' : 'Ver mais comentários' }}
            </button>

            <p v-if="!comments.length && !commentsLoading" class="pm-no-comments">
              Seja o primeiro a comentar.
            </p>
          </div>

          <!-- Actions -->
          <div class="pm-actions">
            <div class="pm-actions__row">
              <div class="pm-actions__group">
                <button
                  v-if="!isOwner"
                  class="pm-icon-btn"
                  :class="{ 'pm-icon-btn--liked': post.likedByMe }"
                  type="button"
                  :disabled="likePending"
                  :aria-label="post.likedByMe ? 'Remover curtida' : 'Curtir'"
                  @click="handleToggleLike"
                >
                  <AppIcon name="heart" />
                </button>

                <button class="pm-icon-btn" type="button" aria-label="Comentar" @click="$el.closest('.pm-side').querySelector('.pm-comment-input').focus()">
                  <AppIcon name="comment" />
                </button>

                <button class="pm-icon-btn" type="button" aria-label="Compartilhar">
                  <AppIcon name="share" />
                </button>

                <button
                  v-if="!isOwner"
                  class="pm-icon-btn"
                  :class="{ 'pm-icon-btn--reposted': post.repostedByMe }"
                  type="button"
                  :disabled="repostPending"
                  :aria-label="post.repostedByMe ? 'Remover republicação' : 'Republicar'"
                  @click="handleToggleRepost"
                >
                  <AppIcon name="repost" />
                </button>
              </div>

              <button
                class="pm-icon-btn"
                :class="{ 'pm-icon-btn--saved': post.savedByMe }"
                type="button"
                :disabled="savePending"
                :aria-label="post.savedByMe ? 'Remover dos salvos' : 'Salvar'"
                @click="handleToggleSave"
              >
                <AppIcon name="save" />
              </button>
            </div>

            <p class="pm-likes">{{ likesLabel }}</p>
            <time class="pm-date" :datetime="post.createdAt">{{ publishedLabel }}</time>
          </div>

          <!-- Add comment -->
          <form class="pm-comment-form" @submit.prevent="handleCommentSubmit">
            <input
              v-model="commentText"
              class="pm-comment-input"
              type="text"
              maxlength="2200"
              placeholder="Adicione um comentário..."
            />
            <button
              class="pm-comment-submit"
              :class="{ 'pm-comment-submit--active': trimmedComment }"
              type="submit"
              :disabled="!trimmedComment || isSubmittingComment"
            >
              {{ isSubmittingComment ? '...' : 'Publicar' }}
            </button>
          </form>
        </div>
      </article>

      <!-- Next arrow -->
      <button
        v-if="hasNext"
        class="pm-nav pm-nav--next"
        type="button"
        aria-label="Próximo post"
        @click="goToNav(navIdx + 1)"
      >
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <path d="M9 18l6-6-6-6"/>
        </svg>
      </button>

    </div>
  </Teleport>
</template>

<style scoped>
/* ── Overlay ── */
.pm-overlay {
  position: fixed;
  inset: 0;
  z-index: 100;
  background: rgba(0, 0, 0, 0.85);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1.5rem;
  container-type: inline-size;
}

.pm-nav {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  z-index: 102;
  display: grid;
  place-items: center;
  width: 2.5rem;
  height: 2.5rem;
  border-radius: 50%;
  border: 0;
  background: rgba(255, 255, 255, 0.12);
  color: #fff;
  cursor: pointer;
  transition: background 150ms ease;
}

.pm-nav:hover {
  background: rgba(255, 255, 255, 0.25);
}

.pm-nav--prev { left: 1rem; }
.pm-nav--next { right: 1rem; }

.pm-close {
  position: fixed;
  top: 1rem;
  right: 1.25rem;
  z-index: 101;
  display: grid;
  place-items: center;
  width: 2.25rem;
  height: 2.25rem;
  color: #fff;
  background: none;
  border: 0;
  cursor: pointer;
  opacity: 0.85;
  transition: opacity 150ms ease;
}

.pm-close:hover {
  opacity: 1;
}

/* ── States ── */
.pm-loading,
.pm-error {
  color: #fff;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1rem;
  font-size: 1rem;
}

.pm-error button {
  padding: 0.5rem 1.25rem;
  border-radius: 0.5rem;
  background: var(--app-accent);
  color: #fff;
  border: 0;
  cursor: pointer;
}

/* ── Card ── */
.pm-card {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 400px;
  width: min(88cqi, 1100px);
  max-height: calc(100vh - 3rem);
  background: var(--app-surface);
  border-radius: 4px;
  overflow: hidden;
}

/* ── Media ── */
.pm-media {
  position: relative;
  background: #111;
  display: grid;
  place-items: center;
  min-height: 520px;
  overflow: hidden;
}

.pm-media__el {
  width: 100%;
  height: 100%;
  max-height: calc(100vh - 3rem);
}

/* ── Side panel ── */
.pm-side {
  display: flex;
  flex-direction: column;
  background: var(--app-surface);
  border-left: 1px solid var(--app-border);
  min-width: 0;
  max-height: calc(100vh - 3rem);
}

/* ── Header ── */
.pm-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 0.875rem 1rem;
  border-bottom: 1px solid var(--app-border);
  flex-shrink: 0;
}

.pm-head__author {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  color: inherit;
  text-decoration: none;
  min-width: 0;
}

.pm-head__username {
  font-size: 0.9rem;
  font-weight: 700;
  color: var(--app-text);
}

.pm-head__end {
  position: relative;
  flex-shrink: 0;
}

/* ── Owner menu ── */
.pm-owner-wrap {
  position: relative;
}

.pm-owner-menu {
  position: absolute;
  top: calc(100% + 0.5rem);
  right: 0;
  z-index: 10;
  min-width: 180px;
  background: var(--app-surface-strong);
  border: 1px solid var(--app-border);
  border-radius: 0.75rem;
  overflow: hidden;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.5);
}

.pm-owner-menu__item {
  display: block;
  width: 100%;
  padding: 0.85rem 1rem;
  text-align: left;
  font-size: 0.9rem;
  font-weight: 500;
  color: var(--app-text);
  background: none;
  border: 0;
  cursor: pointer;
  transition: background 120ms ease;
}

.pm-owner-menu__item:hover {
  background: var(--app-surface-soft);
}

.pm-owner-menu__item--danger {
  color: #ff5c5c;
  font-weight: 700;
}

/* ── Body (scrollable) ── */
.pm-body {
  flex: 1;
  overflow-y: auto;
  padding: 0.875rem 1rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.pm-body::-webkit-scrollbar {
  width: 4px;
}

.pm-body::-webkit-scrollbar-thumb {
  background: var(--app-border);
  border-radius: 2px;
}

/* ── Comments ── */
.pm-comment {
  display: flex;
  gap: 0.75rem;
  align-items: flex-start;
}

.pm-comment__avatar-link {
  flex-shrink: 0;
}

.pm-comment__content {
  flex: 1;
  min-width: 0;
}

.pm-comment__text {
  margin: 0;
  font-size: 0.88rem;
  line-height: 1.55;
  color: var(--app-text);
  word-break: break-word;
}

.pm-comment__name {
  font-weight: 700;
  color: var(--app-text);
  text-decoration: none;
  margin-right: 0.35rem;
}

.pm-comment__name:hover {
  text-decoration: underline;
}

.pm-comment__meta {
  display: flex;
  align-items: center;
  gap: 0.85rem;
  margin-top: 0.3rem;
  font-size: 0.75rem;
  color: var(--app-muted);
}

.pm-comment__delete {
  background: none;
  border: 0;
  color: var(--app-muted);
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
  padding: 0;
}

.pm-comment__delete:hover {
  color: var(--app-danger);
}

.pm-no-comments {
  margin: 0;
  color: var(--app-muted);
  font-size: 0.88rem;
  text-align: center;
  padding: 1.5rem 0;
}

.pm-load-more {
  display: block;
  width: 100%;
  padding: 0.5rem 0;
  background: none;
  border: 0;
  color: var(--app-muted);
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
  text-align: left;
}

.pm-load-more:hover {
  color: var(--app-text);
}

/* ── Actions ── */
.pm-actions {
  padding: 0.5rem 1rem 0.25rem;
  border-top: 1px solid var(--app-border);
  flex-shrink: 0;
}

.pm-actions__row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.25rem 0;
}

.pm-actions__group {
  display: flex;
  align-items: center;
  gap: 0.1rem;
}

.pm-icon-btn {
  display: grid;
  place-items: center;
  width: 2.4rem;
  height: 2.4rem;
  padding: 0;
  border: 0;
  color: var(--app-text);
  background: none;
  cursor: pointer;
  transition: color 150ms ease, transform 120ms ease;
}

.pm-icon-btn:hover {
  color: var(--app-muted);
}

.pm-icon-btn--liked {
  color: var(--app-danger);
}

.pm-icon-btn--liked:hover {
  color: var(--app-danger);
}

.pm-icon-btn--saved {
  color: #ffd60a;
}

.pm-icon-btn--saved:hover {
  color: #ffd60a;
}

.pm-icon-btn--reposted {
  color: #3cc663;
}

.pm-icon-btn--reposted:hover {
  color: #3cc663;
}

.pm-icon-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.pm-likes {
  margin: 0.1rem 0 0;
  font-size: 0.9rem;
  font-weight: 700;
  color: var(--app-text);
}

.pm-date {
  display: block;
  margin-top: 0.2rem;
  font-size: 0.7rem;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--app-muted);
  padding-bottom: 0.5rem;
}

/* ── Comment form ── */
.pm-comment-form {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.6rem 1rem;
  border-top: 1px solid var(--app-border);
  flex-shrink: 0;
}

.pm-comment-input {
  flex: 1;
  background: transparent;
  border: 0;
  outline: none;
  color: var(--app-text);
  font-size: 0.9rem;
  font-family: inherit;
  padding: 0.5rem 0;
}

.pm-comment-input::placeholder {
  color: var(--app-muted);
}

.pm-comment-submit {
  background: none;
  border: 0;
  color: var(--app-accent);
  font-size: 0.88rem;
  font-weight: 700;
  cursor: pointer;
  opacity: 0.4;
  pointer-events: none;
  transition: opacity 150ms ease;
}

.pm-comment-submit--active {
  opacity: 1;
  pointer-events: auto;
}

/* ── Mobile ── */
@media (max-width: 880px) {
  .pm-overlay {
    align-items: center;
    justify-content: center;
    padding: 1rem;
    background: rgba(0, 0, 0, 0.75);
  }

  .pm-close {
    top: 0.75rem;
    right: 0.75rem;
    background: rgba(0, 0, 0, 0.45);
    border-radius: 50%;
    width: 2rem;
    height: 2rem;
  }

  .pm-nav { display: none; }

  /* Card centralizado com margem nos 4 lados */
  .pm-card {
    display: flex;
    flex-direction: column;
    width: 100%;
    max-height: 90vh;
    border-radius: 1rem;
    overflow-y: auto;
    overflow-x: hidden;
  }

  /* pm-side some como caixa — filhos viram flex items do pm-card */
  .pm-side { display: contents; }

  /* Ordem: header → imagem → body → actions → form */
  .pm-head {
    order: 1;
    padding: 0.45rem 1rem;
    border-bottom: 1px solid var(--app-border);
  }

  .pm-media {
    order: 2;
    min-height: auto;
    max-height: none;
    overflow: hidden;
    aspect-ratio: 1 / 1;
  }

  .pm-media__el { width: 100%; height: 100%; }

  .pm-body {
    order: 3;
    max-height: 12vh;
    overflow-y: auto;
    border-top: 1px solid var(--app-border);
  }

  .pm-actions {
    order: 4;
    border-top: 1px solid var(--app-border);
  }

  .pm-comment-form { order: 5; }
}
</style>
