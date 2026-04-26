<script setup>
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import * as postsService from '@/services/posts.service'
import { extractErrorMessage } from '@/services/api'
import { normalizePost } from '@/stores/feed'

const posts = ref([])
const currentPage = ref(1)
const hasMore = ref(false)
const isLoading = ref(false)
const loadError = ref('')

function formatCount(n) {
  if (n >= 1_000_000) return (n / 1_000_000).toFixed(1).replace('.0', '') + 'M'
  if (n >= 1000) return (n / 1000).toFixed(1).replace('.0', '') + 'mil'
  return String(n)
}

async function loadPosts({ reset = true } = {}) {
  if (isLoading.value) return
  isLoading.value = true
  loadError.value = ''

  try {
    const page = reset ? 1 : currentPage.value + 1
    const response = await postsService.explore(18, page)
    const normalized = (response.data ?? []).map(normalizePost).filter(Boolean)

    posts.value = reset ? normalized : [...posts.value, ...normalized]
    currentPage.value = Number(response.current_page ?? page)
    hasMore.value = Boolean(response.next_page_url)
  } catch (error) {
    loadError.value = extractErrorMessage(error, 'Não foi possível carregar o explorar agora.')
  } finally {
    isLoading.value = false
  }
}

onMounted(() => loadPosts({ reset: true }))
</script>

<template>
  <section class="explore">
    <!-- Banner -->
    <header class="explore__banner">
      <h1 class="explore__banner-title">Explore o mundo!</h1>
      <p class="explore__banner-sub">Descubra novas pessoas, lugares e momentos.</p>
    </header>

    <p v-if="loadError" class="explore__error" role="alert">{{ loadError }}</p>

    <!-- Grid -->
    <div v-if="posts.length > 0" class="explore__grid">
      <RouterLink
        v-for="post in posts"
        :key="post.id"
        class="explore__tile"
        :to="{ name: 'post-detalhes', params: { postId: post.id } }"
        :aria-label="`Post de @${post.author.username}`"
      >
        <video
          v-if="post.isVideo"
          class="explore__img"
          :src="post.imageUrl"
          preload="metadata"
          muted
          playsinline
        />
        <img
          v-else
          class="explore__img"
          :src="post.imageUrl"
          :alt="post.imageAlt || ''"
          loading="lazy"
        />

        <!-- Vídeo badge -->
        <span v-if="post.isVideo" class="explore__badge" aria-hidden="true">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
            <path d="M7 4v16l13-8L7 4Z"/>
          </svg>
        </span>

        <!-- Hover overlay -->
        <span class="explore__hover" aria-hidden="true">
          <span class="explore__stat">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
              <path d="M12 21s-7-4.5-9-10c-1.5-4 1.5-7 4.5-7 1.8 0 3.5 1 4.5 2.5C13 5 14.7 4 16.5 4c3 0 6 3 4.5 7-2 5.5-9 10-9 10Z"/>
            </svg>
            {{ formatCount(post.likesCount ?? 0) }}
          </span>
          <span class="explore__stat">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
              <path d="M21 12a8 8 0 0 1-11.7 7.1L4 21l1.9-5.3A8 8 0 1 1 21 12Z"/>
            </svg>
            {{ formatCount(post.commentsCount ?? 0) }}
          </span>
        </span>
      </RouterLink>
    </div>

    <!-- Skeleton -->
    <div v-else-if="isLoading" class="explore__skeleton">
      <div v-for="n in 9" :key="n" class="explore__skeleton-cell" />
    </div>

    <!-- Empty -->
    <div v-else-if="!isLoading" class="explore__empty">
      <p>Nenhum post para explorar ainda.</p>
    </div>

    <!-- Load more -->
    <div v-if="hasMore" class="explore__more">
      <button
        class="explore__more-btn"
        type="button"
        :disabled="isLoading"
        @click="loadPosts({ reset: false })"
      >
        {{ isLoading ? 'Carregando...' : 'Carregar mais' }}
      </button>
    </div>
  </section>
</template>

<style scoped>
.explore {
  display: grid;
  gap: 0;
}

/* ── Banner ── */
.explore__banner {
  position: relative;
  height: 180px;
  border-radius: 0.85rem;
  overflow: hidden;
  margin-bottom: 4px;
  display: flex;
  flex-direction: column;
  justify-content: center;
  padding: 0 2rem;
  color: #fff;
  background:
    linear-gradient(135deg, rgba(0, 0, 0, 0.6) 0%, rgba(0, 0, 0, 0.25) 100%),
    linear-gradient(135deg, #0f0c29, #302b63, #24243e);
}

.explore__banner::before {
  content: '';
  position: absolute;
  inset: 0;
  background:
    radial-gradient(ellipse at 20% 50%, rgba(0, 149, 246, 0.25) 0%, transparent 60%),
    radial-gradient(ellipse at 80% 30%, rgba(120, 60, 220, 0.2) 0%, transparent 55%);
  pointer-events: none;
}

.explore__banner-title {
  position: relative;
  margin: 0;
  font-size: clamp(1.6rem, 4vw, 2.2rem);
  font-weight: 700;
  letter-spacing: -0.01em;
  text-shadow: 0 2px 12px rgba(0, 0, 0, 0.4);
}

.explore__banner-sub {
  position: relative;
  margin: 0.4rem 0 0;
  font-size: 0.95rem;
  opacity: 0.9;
  text-shadow: 0 1px 8px rgba(0, 0, 0, 0.4);
}

/* ── Error ── */
.explore__error {
  margin: 0 0 4px;
  padding: 0.95rem 1rem;
  border: 1px solid rgba(255, 48, 64, 0.28);
  border-radius: 0.75rem;
  color: #ffb4ba;
  background: rgba(255, 48, 64, 0.08);
}

/* ── Grid ── */
.explore__grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 3px;
}

.explore__tile {
  position: relative;
  aspect-ratio: 4 / 5;
  overflow: hidden;
  display: block;
  background: #000;
}

.explore__img {
  width: 100%;
  height: 100%;
  object-fit: contain;
  display: block;
  transition: transform 300ms ease;
}

.explore__tile:hover .explore__img {
  transform: scale(1.04);
}

.explore__badge {
  position: absolute;
  top: 0.6rem;
  right: 0.6rem;
  color: #fff;
  filter: drop-shadow(0 1px 3px rgba(0, 0, 0, 0.55));
  line-height: 0;
}

.explore__hover {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 1.5rem;
  background: rgba(0, 0, 0, 0.45);
  color: #fff;
  font-weight: 700;
  opacity: 0;
  transition: opacity 150ms ease;
}

.explore__tile:hover .explore__hover,
.explore__tile:focus-visible .explore__hover {
  opacity: 1;
}

.explore__stat {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  font-size: 0.9rem;
}

/* ── Skeleton ── */
.explore__skeleton {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 3px;
}

.explore__skeleton-cell {
  aspect-ratio: 4 / 5;
  background: var(--app-surface-soft);
  animation: pulse 1.4s ease-in-out infinite;
}

.explore__skeleton-cell:nth-child(2) { animation-delay: 0.1s; }
.explore__skeleton-cell:nth-child(3) { animation-delay: 0.2s; }
.explore__skeleton-cell:nth-child(4) { animation-delay: 0.15s; }
.explore__skeleton-cell:nth-child(5) { animation-delay: 0.25s; }
.explore__skeleton-cell:nth-child(6) { animation-delay: 0.3s; }

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.4; }
}

/* ── Empty ── */
.explore__empty {
  padding: 3rem 1rem;
  text-align: center;
  color: var(--app-muted);
}

/* ── Load more ── */
.explore__more {
  display: flex;
  justify-content: center;
  padding: 1.25rem 0 0.5rem;
}

.explore__more-btn {
  min-width: 14rem;
  padding: 0.8rem 1.1rem;
  border: 1px solid var(--app-border);
  border-radius: 0.8rem;
  color: var(--app-text);
  font-weight: 700;
  background: var(--app-surface-soft);
  transition: background 180ms ease, border-color 180ms ease;
}

.explore__more-btn:hover:not(:disabled),
.explore__more-btn:focus-visible {
  border-color: var(--app-border-strong);
  background: #1b1b1b;
}

.explore__more-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* ── Mobile ── */
@media (max-width: 768px) {
  .explore__banner {
    height: 140px;
    padding: 0 1.25rem;
  }

  .explore__banner-title {
    font-size: 1.5rem;
  }

  .explore__grid,
  .explore__skeleton {
    gap: 2px;
  }
}
</style>
