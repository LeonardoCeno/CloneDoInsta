<script setup>
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import AppIcon from '@/components/layout/AppIcon.vue'
import MediaDisplay from '@/components/shared/MediaDisplay.vue'
import * as postsService from '@/services/posts.service'
import { extractErrorMessage } from '@/services/api'
import { normalizePost } from '@/stores/feed'

const posts = ref([])
const currentPage = ref(1)
const hasMore = ref(false)
const isLoading = ref(false)
const loadError = ref('')

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
    <header class="explore__header">
      <span class="explore__eyebrow">Conteúdo da rede</span>
      <h2>Explorar</h2>
      <p>Posts de todos os perfis da plataforma.</p>
    </header>

    <p v-if="loadError" class="explore__error" role="alert">{{ loadError }}</p>

    <div v-if="posts.length > 0" class="explore__grid">
      <RouterLink
        v-for="post in posts"
        :key="post.id"
        class="explore__cell"
        :to="{ name: 'post-detalhes', params: { postId: post.id } }"
        :aria-label="`Post de @${post.author.username}`"
      >
        <MediaDisplay
          :src="post.imageUrl"
          :alt="post.imageAlt"
          :is-video="post.isVideo"
          :thumbnail="true"
          class="explore__img"
        />
        <div class="explore__overlay" aria-hidden="true">
          <span class="explore__stat">
            <AppIcon name="heart" />
            {{ post.likesCount }}
          </span>
          <span class="explore__stat">
            <AppIcon name="comment" />
            {{ post.commentsCount }}
          </span>
        </div>
      </RouterLink>
    </div>

    <div v-else-if="isLoading" class="explore__skeleton">
      <div v-for="n in 9" :key="n" class="explore__skeleton-cell" />
    </div>

    <div v-else-if="!isLoading" class="explore__empty">
      <p>Nenhum post para explorar ainda.</p>
    </div>

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
  gap: 1.25rem;
}

.explore__header {
  padding: 1.4rem;
  border-radius: 1.75rem;
  background: var(--app-surface);
}

.explore__eyebrow {
  display: inline-block;
  margin-bottom: 0.35rem;
  color: var(--app-accent-strong);
  font-size: 0.78rem;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.explore__header h2 {
  margin: 0 0 0.35rem;
  font-size: clamp(1.6rem, 4vw, 2.3rem);
  font-weight: 800;
}

.explore__header p {
  margin: 0;
  color: var(--app-muted);
}

.explore__error {
  margin: 0;
  padding: 0.95rem 1rem;
  border: 1px solid rgba(255, 48, 64, 0.28);
  border-radius: 1rem;
  color: #ffb4ba;
  background: rgba(255, 48, 64, 0.08);
}

/* Grid */
.explore__grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 3px;
  border-radius: 1rem;
  overflow: hidden;
}

.explore__cell {
  position: relative;
  aspect-ratio: 3 / 5;
  overflow: hidden;
  display: block;
  background: var(--app-surface-soft);
}

.explore__img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
  transition: transform 300ms ease;
}

.explore__cell:hover .explore__img {
  transform: scale(1.04);
}

.explore__overlay {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 1.25rem;
  background: rgba(0, 0, 0, 0.45);
  opacity: 0;
  transition: opacity 200ms ease;
}

.explore__cell:hover .explore__overlay,
.explore__cell:focus-visible .explore__overlay {
  opacity: 1;
}

.explore__stat {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  color: #fff;
  font-size: 0.9rem;
  font-weight: 700;
}

.explore__stat .app-icon {
  width: 18px;
  height: 18px;
}

/* Skeleton */
.explore__skeleton {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 3px;
  border-radius: 1rem;
  overflow: hidden;
}

.explore__skeleton-cell {
  aspect-ratio: 3 / 5;
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
  50% { opacity: 0.45; }
}

.explore__empty {
  padding: 2rem 1rem;
  text-align: center;
  color: var(--app-muted);
}

.explore__more {
  display: flex;
  justify-content: center;
  padding: 0.5rem 0 1rem;
}

.explore__more-btn {
  min-width: 14rem;
  padding: 0.8rem 1.1rem;
  border: 1px solid var(--app-border);
  border-radius: 0.8rem;
  color: var(--app-text);
  font-weight: 700;
  background: var(--app-surface-soft);
  transition:
    background-color 180ms ease,
    border-color 180ms ease;
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
</style>
