<script setup>
import { onMounted, onUnmounted, ref, computed, watch, nextTick } from 'vue'
import { RouterLink } from 'vue-router'
import AppIcon from '@/components/layout/AppIcon.vue'
import ProfileAvatar from '@/components/profile/ProfileAvatar.vue'
import MediaDisplay from '@/components/shared/MediaDisplay.vue'
import * as postsService from '@/services/posts.service'
import * as likesService from '@/services/likes.service'
import * as savesService from '@/services/saves.service'
import * as followsService from '@/services/follows.service'
import * as repostsService from '@/services/reposts.service'
import { normalizePost } from '@/stores/feed'
import { useAuth } from '@/composables/useAuth'
import { useToastStore } from '@/stores/toast'

const { currentUser } = useAuth()
const toastStore = useToastStore()

const posts = ref([])
const currentPage = ref(1)
const hasMore = ref(false)
const isLoading = ref(false)
const activeIndex = ref(0)

const containerRef = ref(null)
const likePending = ref(new Set())
const savePending = ref(new Set())
const repostPending = ref(new Set())
const followPending = ref(new Set())
const followedIds = ref(new Set())
const followPendingIds = ref(new Set())

const activePost = computed(() => posts.value[activeIndex.value] ?? null)

async function loadPosts({ reset = true } = {}) {
  if (isLoading.value) return
  isLoading.value = true

  try {
    const page = reset ? 1 : currentPage.value + 1
    const response = await postsService.explore(27, page)
    const normalized = (response.data ?? []).map(normalizePost).filter(Boolean)

    posts.value = reset ? normalized : [...posts.value, ...normalized]
    currentPage.value = Number(response.current_page ?? page)
    hasMore.value = Boolean(response.next_page_url)

    for (const p of normalized) {
      if (p.author?.isFollowing) followedIds.value.add(p.author.id)
    }
  } finally {
    isLoading.value = false
  }
}

function scrollTo(index) {
  if (index < 0 || index >= posts.value.length) return
  activeIndex.value = index
  const items = containerRef.value?.querySelectorAll('.reel-item')
  items?.[index]?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

function goNext() {
  if (activeIndex.value < posts.value.length - 1) {
    scrollTo(activeIndex.value + 1)
    if (activeIndex.value >= posts.value.length - 3 && hasMore.value) {
      loadPosts({ reset: false })
    }
  } else if (!hasMore.value && !isLoading.value) {
    reloadReels()
  }
}

function goPrev() {
  if (activeIndex.value > 0) {
    scrollTo(activeIndex.value - 1)
  }
}

function onScroll() {
  const container = containerRef.value
  if (!container) return
  const items = container.querySelectorAll('.reel-item')
  let nearest = 0
  let minDist = Infinity
  items.forEach((el, i) => {
    const dist = Math.abs(el.getBoundingClientRect().top - container.getBoundingClientRect().top)
    if (dist < minDist) { minDist = dist; nearest = i }
  })
  activeIndex.value = nearest
  if (nearest >= posts.value.length - 3 && hasMore.value && !isLoading.value) {
    loadPosts({ reset: false })
  }
}

function onKeydown(e) {
  if (e.key === 'ArrowDown') { e.preventDefault(); goNext() }
  if (e.key === 'ArrowUp') { e.preventDefault(); goPrev() }
  if (e.key === ' ') {
    e.preventDefault()
    const video = getVideoAt(activeIndex.value)
    if (!video) return
    if (video.paused) {
      video.play().catch(() => {})
      showFlash('play')
    } else {
      video.pause()
      showFlash('pause')
    }
  }
}

async function toggleLike(post) {
  if (likePending.value.has(post.id)) return
  const next = new Set(likePending.value)
  next.add(post.id)
  likePending.value = next

  try {
    if (post.likedByMe) {
      await likesService.unlike(post.id)
      post.likedByMe = false
      post.likesCount = Math.max(0, post.likesCount - 1)
    } else {
      await likesService.like(post.id)
      post.likedByMe = true
      post.likesCount += 1
    }
  } finally {
    const n = new Set(likePending.value)
    n.delete(post.id)
    likePending.value = n
  }
}

async function toggleSave(post) {
  if (savePending.value.has(post.id)) return
  const next = new Set(savePending.value)
  next.add(post.id)
  savePending.value = next

  try {
    if (post.savedByMe) {
      await savesService.unsave(post.id)
      post.savedByMe = false
    } else {
      await savesService.save(post.id)
      post.savedByMe = true
    }
  } finally {
    const n = new Set(savePending.value)
    n.delete(post.id)
    savePending.value = n
  }
}

async function toggleRepost(post) {
  if (repostPending.value.has(post.id)) return
  repostPending.value = new Set([...repostPending.value, post.id])

  try {
    if (post.repostedByMe) {
      await repostsService.unrepost(post.id)
      post.repostedByMe = false
    } else {
      await repostsService.repost(post.id)
      post.repostedByMe = true
      toastStore.show('Vídeo republicado!', 'success')
    }
  } finally {
    repostPending.value = new Set([...repostPending.value].filter((id) => id !== post.id))
  }
}

async function toggleFollow(author) {
  if (!author?.id || followPending.value.has(author.id)) return
  followPending.value = new Set([...followPending.value, author.id])

  try {
    if (followedIds.value.has(author.id)) {
      await followsService.unfollow(author.id)
      followedIds.value = new Set([...followedIds.value].filter((id) => id !== author.id))
    } else if (followPendingIds.value.has(author.id)) {
      await followsService.unfollow(author.id)
      followPendingIds.value = new Set([...followPendingIds.value].filter((id) => id !== author.id))
    } else {
      const result = await followsService.follow(author.id)
      if (result.status === 'pending') {
        followPendingIds.value = new Set([...followPendingIds.value, author.id])
      } else {
        followedIds.value = new Set([...followedIds.value, author.id])
      }
    }
  } finally {
    followPending.value = new Set([...followPending.value].filter((id) => id !== author.id))
  }
}

function isOwnPost(post) {
  return post.author?.id === currentUser.value?.id
}

function formatCount(n) {
  if (n >= 1_000_000) return `${(n / 1_000_000).toFixed(1)} M`
  if (n >= 1_000) return `${(n / 1_000).toFixed(1)} mil`
  return String(n)
}

const lightboxSrc = ref(null)
const isMuted = ref(false)
const volume = ref(0.5)
const showVolumeSlider = ref(false)
const flashIcon = ref(null)
let flashTimer = null
let hideVolumeTimer = null
let sliderDragging = false

function onVolumeEnter() {
  clearTimeout(hideVolumeTimer)
  showVolumeSlider.value = true
}

function onVolumeLeave() {
  if (sliderDragging) return
  hideVolumeTimer = setTimeout(() => { showVolumeSlider.value = false }, 1000)
}

function onSliderMouseDown() {
  sliderDragging = true
  clearTimeout(hideVolumeTimer)
}

function onSliderMouseUp() {
  sliderDragging = false
  onVolumeLeave()
}

function getVideoAt(idx) {
  const items = containerRef.value?.querySelectorAll('.reel-item')
  return items?.[idx]?.querySelector('video') ?? null
}

function showFlash(icon) {
  flashIcon.value = icon
  clearTimeout(flashTimer)
  flashTimer = setTimeout(() => { flashIcon.value = null }, 700)
}

function handleMediaClick(post, idx) {
  if (!post.isVideo) {
    lightboxSrc.value = post.imageUrl
    return
  }
  const video = getVideoAt(idx)
  if (!video) return
  if (video.paused) {
    video.play().catch(() => {})
    showFlash('play')
  } else {
    video.pause()
    showFlash('pause')
  }
}


function applyVolumeToVideo(video) {
  if (!video) return
  video.muted = isMuted.value
  video.volume = volume.value
}

function toggleMute() {
  isMuted.value = !isMuted.value
  applyVolumeToVideo(getVideoAt(activeIndex.value))
}

function setVolume(val) {
  volume.value = val
  const video = getVideoAt(activeIndex.value)
  if (!video) return
  video.volume = val
  if (val === 0) {
    isMuted.value = true
    video.muted = true
  } else if (isMuted.value) {
    isMuted.value = false
    video.muted = false
  }
}

watch(activeIndex, (newIdx, oldIdx) => {
  getVideoAt(oldIdx)?.pause()
  const next = getVideoAt(newIdx)
  if (next) {
    applyVolumeToVideo(next)
    next.play().catch(() => {})
  }
})

async function reloadReels() {
  await loadPosts({ reset: true })
  activeIndex.value = 0
  await nextTick()
  containerRef.value?.scrollTo({ top: 0, behavior: 'smooth' })
  const first = getVideoAt(0)
  if (first) {
    applyVolumeToVideo(first)
    first.play().catch(() => {})
  }
}

let touchStartY = 0

function onWheel(e) {
  if (activeIndex.value === posts.value.length - 1 && !hasMore.value && !isLoading.value && e.deltaY > 0) {
    reloadReels()
  }
}

function onTouchStart(e) {
  touchStartY = e.touches[0].clientY
}

function onTouchEnd(e) {
  const deltaY = touchStartY - e.changedTouches[0].clientY
  if (activeIndex.value === posts.value.length - 1 && !hasMore.value && !isLoading.value && deltaY > 40) {
    reloadReels()
  }
}

onMounted(async () => {
  await loadPosts({ reset: true })
  window.addEventListener('keydown', onKeydown)
  await nextTick()
  const first = getVideoAt(0)
  if (first) {
    applyVolumeToVideo(first)
    first.play().catch(() => {})
  }
})

onUnmounted(() => {
  window.removeEventListener('keydown', onKeydown)
})
</script>

<template>
  <div ref="containerRef" class="reels" @scroll.passive="onScroll" @wheel.passive="onWheel" @touchstart.passive="onTouchStart" @touchend.passive="onTouchEnd">
    <!-- Loading skeleton -->
    <div v-if="isLoading && posts.length === 0" class="reels__skeletons">
      <div v-for="n in 3" :key="n" class="reels__skeleton-item" />
    </div>

    <div
      v-for="(post, idx) in posts"
      :key="post.id"
      class="reel-item"
      :class="{ 'is-active': idx === activeIndex }"
    >
      <!-- Media -->
      <div class="reel-item__media">
        <div
          class="reel-item__media-link"
          :class="{ 'reel-item__media-link--photo': !post.isVideo }"
          @click="handleMediaClick(post, idx)"
        >
          <MediaDisplay
            :src="post.imageUrl"
            :alt="post.imageAlt"
            :is-video="post.isVideo"
            :muted="isMuted"
            :loop="true"
            class="reel-item__img"
          />
        </div>

        <!-- Play/pause flash -->
        <Transition name="flash">
          <div v-if="post.isVideo && flashIcon && idx === activeIndex" class="reel-item__flash">
            <AppIcon :name="flashIcon" />
          </div>
        </Transition>

        <!-- Bottom overlay: author + caption -->
        <div class="reel-item__overlay">
          <RouterLink
            :to="{ name: 'perfil', query: { user: post.author.username } }"
            class="reel-item__author"
          >
            <ProfileAvatar
              :name="post.author.name"
              :username="post.author.username"
              :avatar-url="post.author.avatarUrl"
              :colors="post.author.colors"
              size="sm"
            />
            <span class="reel-item__username">@{{ post.author.username }}</span>
          </RouterLink>

          <button
            v-if="!isOwnPost(post)"
            class="reel-item__follow-btn"
            type="button"
            :disabled="followPending.has(post.author.id)"
            @click="toggleFollow(post.author)"
          >
            {{ followedIds.has(post.author.id) ? 'Seguindo' : followPendingIds.has(post.author.id) ? 'Solicitado' : 'Seguir' }}
          </button>

          <p v-if="post.caption" class="reel-item__caption">{{ post.caption }}</p>
        </div>
      </div>

      <!-- Sidebar: actions + nav -->
      <aside class="reel-item__sidebar">
        <!-- Like -->
        <div class="reel-item__action">
          <button
            class="reel-item__action-btn"
            :class="{ 'is-liked': post.likedByMe }"
            type="button"
            :disabled="isOwnPost(post) || likePending.has(post.id)"
            :aria-label="post.likedByMe ? 'Remover curtida' : 'Curtir'"
            @click="toggleLike(post)"
          >
            <AppIcon name="heart" />
          </button>
          <span class="reel-item__action-count">{{ formatCount(post.likesCount) }}</span>
        </div>

        <!-- Comment -->
        <div class="reel-item__action">
          <RouterLink
            class="reel-item__action-btn"
            :to="{ name: 'post-detalhes', params: { postId: post.id } }"
            aria-label="Comentários"
          >
            <AppIcon name="comment" />
          </RouterLink>
          <span class="reel-item__action-count">{{ formatCount(post.commentsCount) }}</span>
        </div>

        <!-- Save -->
        <div class="reel-item__action">
          <button
            class="reel-item__action-btn"
            :class="{ 'is-saved': post.savedByMe }"
            type="button"
            :disabled="savePending.has(post.id)"
            :aria-label="post.savedByMe ? 'Remover dos salvos' : 'Salvar'"
            @click="toggleSave(post)"
          >
            <AppIcon name="save" />
          </button>
        </div>

        <!-- Share -->
        <div class="reel-item__action">
          <RouterLink
            class="reel-item__action-btn"
            :to="{ name: 'post-detalhes', params: { postId: post.id } }"
            aria-label="Compartilhar"
          >
            <AppIcon name="share" />
          </RouterLink>
        </div>

        <!-- Repost -->
        <div v-if="!isOwnPost(post)" class="reel-item__action">
          <button
            class="reel-item__action-btn"
            :class="{ 'is-reposted': post.repostedByMe }"
            type="button"
            :disabled="repostPending.has(post.id)"
            :aria-label="post.repostedByMe ? 'Remover republicação' : 'Republicar'"
            @click="toggleRepost(post)"
          >
            <AppIcon name="repost" />
          </button>
        </div>

        <!-- Volume (only for video reels) -->
        <div
          v-if="post.isVideo"
          class="reel-item__action reel-item__volume-group"
          @mouseenter="onVolumeEnter"
          @mouseleave="onVolumeLeave"
        >
          <div class="reel-item__volume-slider" :class="{ 'is-visible': showVolumeSlider }">
            <input
              type="range"
              min="0"
              max="1"
              step="0.02"
              :value="isMuted ? 0 : volume"
              class="reel-item__slider"
              aria-label="Volume"
              @input="setVolume(+$event.target.value)"
              @mousedown="onSliderMouseDown"
              @mouseup="onSliderMouseUp"
            />
          </div>
          <button
            class="reel-item__action-btn"
            type="button"
            :aria-label="isMuted ? 'Ativar som' : 'Silenciar'"
            @click="toggleMute"
          >
            <AppIcon :name="isMuted ? 'volume-x' : 'volume'" />
          </button>
        </div>

      </aside>
    </div>

    <!-- Empty state -->
    <div v-if="!isLoading && posts.length === 0" class="reels__empty">
      <p>Nenhum post disponível ainda.</p>
    </div>

    <!-- Reload button after last reel -->
    <div v-if="!isLoading && posts.length > 0 && !hasMore" class="reels__reload">
      <button class="reels__reload-btn" type="button" :disabled="isLoading" @click="reloadReels">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/>
          <path d="M3 3v5h5"/>
        </svg>
        Recarregar
      </button>
    </div>
  </div>

  <!-- Nav: fixed right edge, centered vertically -->
  <div v-if="posts.length > 0" class="reels-nav">
    <button
      class="reels-nav__btn"
      type="button"
      :disabled="activeIndex === 0"
      aria-label="Reel anterior"
      @click="goPrev"
    >
      <AppIcon name="chevron-left" />
    </button>
    <button
      class="reels-nav__btn reels-nav__btn--down"
      type="button"
      :disabled="false"
      aria-label="Próximo reel"
      @click="goNext"
    >
      <AppIcon name="chevron-left" />
    </button>
  </div>

  <!-- Lightbox -->
  <Teleport to="body">
    <div v-if="lightboxSrc" class="reel-lightbox" @click="lightboxSrc = null">
      <img :src="lightboxSrc" class="reel-lightbox__img" alt="" @click.stop />
      <button class="reel-lightbox__close" type="button" @click="lightboxSrc = null">✕</button>
    </div>
  </Teleport>
</template>

<style scoped>
.reels {
  height: 100%;
  overflow-y: scroll;
  scroll-snap-type: y mandatory;
  scrollbar-width: none;
  -ms-overflow-style: none;
}

.reels::-webkit-scrollbar {
  display: none;
}

.reel-item {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.85rem;
  height: 100%;
  scroll-snap-align: start;
  scroll-snap-stop: always;
  container-type: inline-size;
}

.reel-item__media {
  position: relative;
  flex: none;
  width: min(calc(89cqi - 1rem), 500px);
  height: 100%;
  border-radius: 0.75rem;
  overflow: hidden;
  background: var(--app-surface);
}

.reel-item__media-link {
  display: block;
  width: 100%;
  height: 100%;
}

.reel-item__media-link--photo {
  cursor: zoom-in;
}

.reel-item__img {
  width: 100%;
  height: 100%;
  object-fit: contain;
  display: block;
}

.reel-item__overlay {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  padding: 1.5rem 1.25rem 1.25rem;
  background: linear-gradient(to top, rgba(0, 0, 0, 0.72) 0%, transparent 100%);
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
}

.reel-item__author {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  text-decoration: none;
  color: #fff;
}

.reel-item__username {
  font-size: 0.93rem;
  font-weight: 700;
  text-shadow: 0 1px 3px rgba(0, 0, 0, 0.5);
}

.reel-item__follow-btn {
  align-self: flex-start;
  padding: 0.3rem 0.85rem;
  border: 1.5px solid rgba(255, 255, 255, 0.85);
  border-radius: 0.5rem;
  background: transparent;
  color: #fff;
  font-size: 0.82rem;
  font-weight: 700;
  cursor: pointer;
  transition: background 150ms ease, color 150ms ease;
}

.reel-item__follow-btn:hover:not(:disabled) {
  background: rgba(255, 255, 255, 0.18);
}

.reel-item__follow-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.reel-item__caption {
  margin: 0;
  color: rgba(255, 255, 255, 0.9);
  font-size: 0.88rem;
  line-height: 1.5;
  text-shadow: 0 1px 3px rgba(0, 0, 0, 0.5);
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

/* Sidebar */
.reel-item__sidebar {
  flex: none;
  align-self: stretch;
  width: 11cqi;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: flex-end;
  gap: 0.7rem;
}

.reel-item__action {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.2rem;
}

.reel-item__volume-group {
  position: relative;
}

.reel-item__volume-slider {
  position: absolute;
  right: calc(100% + 0.5rem);
  top: 50%;
  transform: translateY(-50%);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0.4rem 0.6rem;
  background: rgba(0, 0, 0, 0.72);
  border-radius: 2rem;
  opacity: 0;
  pointer-events: none;
  transition: opacity 150ms ease;
}

.reel-item__volume-slider.is-visible {
  opacity: 1;
  pointer-events: auto;
}

.reel-item__slider {
  -webkit-appearance: none;
  appearance: none;
  width: 80px;
  height: 20px;
  background: transparent;
  cursor: pointer;
}

.reel-item__slider::-webkit-slider-runnable-track {
  height: 4px;
  border-radius: 2px;
  background: rgba(255, 255, 255, 0.35);
}

.reel-item__slider::-webkit-slider-thumb {
  -webkit-appearance: none;
  width: 14px;
  height: 14px;
  border-radius: 50%;
  background: #fff;
  margin-top: -5px;
  cursor: pointer;
}

.reel-item__slider::-moz-range-track {
  height: 4px;
  border-radius: 2px;
  background: rgba(255, 255, 255, 0.35);
}

.reel-item__slider::-moz-range-thumb {
  width: 14px;
  height: 14px;
  border-radius: 50%;
  background: #fff;
  border: none;
  cursor: pointer;
}


.reel-item__action-btn {
  display: grid;
  place-items: center;
  width: 7cqi;
  height: 7cqi;
  padding: 0;
  border: 0;
  border-radius: 0;
  background: none;
  color: var(--app-text);
  text-decoration: none;
  cursor: pointer;
  transition: color 150ms ease;
}

.reel-item__action-btn:hover {
  background: none;
  opacity: 0.8;
}

.reel-item__action-btn.is-liked {
  color: var(--app-danger);
}

.reel-item__action-btn.is-saved {
  color: var(--app-link);
}

.reel-item__action-btn.is-reposted {
  color: #3cc663;
}

.reel-item__action-btn:disabled {
  opacity: 0.35;
  cursor: not-allowed;
}

.reel-item__action-count {
  font-size: 0.72rem;
  color: var(--app-muted);
  font-weight: 600;
  white-space: nowrap;
  line-height: 1;
}

.reels-nav {
  position: fixed;
  right: 1.5rem;
  top: 50%;
  transform: translateY(-50%);
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
  z-index: 10;
}

.reels-nav__btn {
  display: grid;
  place-items: center;
  width: 3.2rem;
  height: 3.2rem;
  padding: 0;
  border: 1px solid var(--app-border);
  border-radius: 50%;
  background: var(--app-surface-soft);
  color: var(--app-text);
  cursor: pointer;
  transition: background 150ms ease;
}

.reels-nav__btn:hover:not(:disabled) {
  background: var(--app-surface);
}

.reels-nav__btn:disabled {
  opacity: 0.25;
  cursor: not-allowed;
}

/* Skeleton */
.reels__skeletons {
  display: flex;
  flex-direction: column;
  height: 100%;
}

.reels__skeleton-item {
  flex: none;
  height: 100%;
  border-radius: 1.25rem;
  background: var(--app-surface-soft);
  animation: reel-pulse 1.4s ease-in-out infinite;
}

@keyframes reel-pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.45; }
}

.reels__empty {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 100%;
  color: var(--app-muted);
}

.reels__reload {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 2rem 0 3rem;
}

.reels__reload-btn {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.7rem 1.4rem;
  border: 1px solid var(--app-border);
  border-radius: 2rem;
  background: var(--app-surface-soft);
  color: var(--app-text);
  font-size: 0.9rem;
  font-weight: 600;
  cursor: pointer;
  transition: background 150ms ease, border-color 150ms ease;
}

.reels__reload-btn:hover:not(:disabled) {
  background: var(--app-border);
  border-color: var(--app-border-strong);
}

.reels__reload-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Play/pause flash */
.reel-item__flash {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  pointer-events: none;
}

.reel-item__flash .app-icon {
  width: 4rem;
  height: 4rem;
  padding: 1rem;
  border-radius: 50%;
  background: rgba(0, 0, 0, 0.45);
  color: #fff;
}

.flash-enter-active {
  transition: opacity 100ms ease;
}
.flash-leave-active {
  transition: opacity 400ms ease;
}
.flash-enter-from,
.flash-leave-to {
  opacity: 0;
}

/* Lightbox */
.reel-lightbox {
  position: fixed;
  inset: 0;
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(0, 0, 0, 0.92);
  cursor: zoom-out;
}

.reel-lightbox__img {
  max-width: 92vw;
  max-height: 92vh;
  object-fit: contain;
  border-radius: 0.75rem;
  cursor: default;
}

.reel-lightbox__close {
  position: absolute;
  top: 1.25rem;
  right: 1.25rem;
  width: 2.5rem;
  height: 2.5rem;
  padding: 0;
  border: 0;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.12);
  color: #fff;
  font-size: 1rem;
  cursor: pointer;
  transition: background 150ms ease;
}

.reel-lightbox__close:hover {
  background: rgba(255, 255, 255, 0.22);
}

/* Rotate chevrons to up/down arrows */
.reels-nav__btn :deep(.app-icon) {
  transform: rotate(90deg);
}

.reels-nav__btn--down :deep(.app-icon) {
  transform: rotate(-90deg);
}
</style>
