<script setup>
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useFeed } from '@/composables/useFeed'
import { extractErrorMessage } from '@/services/api'

const loadError = ref('')

const {
  savedPosts,
  savedHasNext,
  savedLoaded,
  savedLoading,
  fetchSaved,
} = useFeed()

function formatCount(n) {
  if (n >= 1_000_000) return (n / 1_000_000).toFixed(1).replace('.0', '') + 'M'
  if (n >= 1000) return (n / 1000).toFixed(1).replace('.0', '') + 'mil'
  return String(n)
}

onMounted(async () => {
  try {
    await fetchSaved({ reset: true })
  } catch (error) {
    loadError.value = extractErrorMessage(error, 'Não foi possível carregar os posts salvos.')
  }
})

async function handleLoadMore() {
  try {
    await fetchSaved({ reset: false })
  } catch (error) {
    loadError.value = extractErrorMessage(error, 'Não foi possível carregar mais posts.')
  }
}
</script>

<template>
  <section class="saved">
    <h2 class="saved__title">Posts salvos</h2>

    <p v-if="loadError" class="saved__error" role="alert">{{ loadError }}</p>

    <!-- Grid -->
    <div v-if="savedPosts.length > 0" class="saved__grid">
      <RouterLink
        v-for="post in savedPosts"
        :key="post.id"
        class="saved__tile"
        :to="{ name: 'post-detalhes', params: { postId: post.id } }"
        :aria-label="`Post de @${post.author.username}`"
      >
        <video
          v-if="post.isVideo"
          class="saved__img"
          :src="post.imageUrl"
          preload="metadata"
          muted
          playsinline
        />
        <img
          v-else
          class="saved__img"
          :src="post.imageUrl"
          :alt="post.imageAlt || ''"
          loading="lazy"
        />

        <span v-if="post.isVideo" class="saved__badge" aria-hidden="true">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
            <path d="M7 4v16l13-8L7 4Z"/>
          </svg>
        </span>

        <span class="saved__hover" aria-hidden="true">
          <span class="saved__stat">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
              <path d="M12 21s-7-4.5-9-10c-1.5-4 1.5-7 4.5-7 1.8 0 3.5 1 4.5 2.5C13 5 14.7 4 16.5 4c3 0 6 3 4.5 7-2 5.5-9 10-9 10Z"/>
            </svg>
            {{ formatCount(post.likesCount ?? 0) }}
          </span>
          <span class="saved__stat">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
              <path d="M21 12a8 8 0 0 1-11.7 7.1L4 21l1.9-5.3A8 8 0 1 1 21 12Z"/>
            </svg>
            {{ formatCount(post.commentsCount ?? 0) }}
          </span>
        </span>
      </RouterLink>
    </div>

    <!-- Skeleton -->
    <div v-else-if="savedLoading" class="saved__skeleton">
      <div v-for="n in 9" :key="n" class="saved__skeleton-cell" />
    </div>

    <!-- Empty -->
    <div v-else-if="savedLoaded && !savedLoading" class="saved__empty">
      <p>Você ainda não salvou nenhum post.</p>
    </div>

    <!-- Load more -->
    <div v-if="savedHasNext" class="saved__more">
      <button
        class="saved__more-btn"
        type="button"
        :disabled="savedLoading"
        @click="handleLoadMore"
      >
        {{ savedLoading ? 'Carregando...' : 'Carregar mais' }}
      </button>
    </div>
  </section>
</template>

<style scoped>
.saved {
  display: grid;
  gap: 0;
}

.saved__title {
  margin: 0.75rem 0 0.85rem;
  font-size: clamp(1.4rem, 4vw, 2rem);
  font-weight: 800;
  color: var(--app-text);
}

.saved__error {
  margin: 0 0 4px;
  padding: 0.95rem 1rem;
  border: 1px solid rgba(255, 48, 64, 0.28);
  border-radius: 0.75rem;
  color: #ffb4ba;
  background: rgba(255, 48, 64, 0.08);
}

/* ── Grid ── */
.saved__grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 3px;
}

.saved__tile {
  position: relative;
  aspect-ratio: 4 / 5;
  overflow: hidden;
  display: block;
  background: #111;
}

.saved__img {
  width: 100%;
  height: 100%;
  object-fit: contain;
  display: block;
  transition: transform 300ms ease;
}

.saved__tile:hover .saved__img {
  transform: scale(1.04);
}

.saved__badge {
  position: absolute;
  top: 0.6rem;
  right: 0.6rem;
  color: #fff;
  filter: drop-shadow(0 1px 3px rgba(0, 0, 0, 0.55));
  line-height: 0;
}

.saved__hover {
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

.saved__tile:hover .saved__hover,
.saved__tile:focus-visible .saved__hover {
  opacity: 1;
}

.saved__stat {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  font-size: 0.9rem;
}

/* ── Skeleton ── */
.saved__skeleton {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 3px;
}

.saved__skeleton-cell {
  aspect-ratio: 4 / 5;
  background: var(--app-surface-soft);
  animation: pulse 1.4s ease-in-out infinite;
}

.saved__skeleton-cell:nth-child(2) { animation-delay: 0.1s; }
.saved__skeleton-cell:nth-child(3) { animation-delay: 0.2s; }
.saved__skeleton-cell:nth-child(4) { animation-delay: 0.15s; }
.saved__skeleton-cell:nth-child(5) { animation-delay: 0.25s; }
.saved__skeleton-cell:nth-child(6) { animation-delay: 0.3s; }

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.4; }
}

/* ── Empty ── */
.saved__empty {
  padding: 3rem 1rem;
  text-align: center;
  color: var(--app-muted);
  font-size: 0.9rem;
}

/* ── Load more ── */
.saved__more {
  display: flex;
  justify-content: center;
  padding: 1.25rem 0 0.5rem;
}

.saved__more-btn {
  min-width: 14rem;
  padding: 0.8rem 1.1rem;
  border: 1px solid var(--app-border);
  border-radius: 0.8rem;
  color: var(--app-text);
  font-weight: 700;
  background: var(--app-surface-soft);
  transition: background 180ms ease, border-color 180ms ease;
}

.saved__more-btn:hover:not(:disabled),
.saved__more-btn:focus-visible {
  border-color: var(--app-border-strong);
  background: #1b1b1b;
}

.saved__more-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

@media (max-width: 768px) {
  .saved__grid,
  .saved__skeleton {
    gap: 2px;
  }
}
</style>
