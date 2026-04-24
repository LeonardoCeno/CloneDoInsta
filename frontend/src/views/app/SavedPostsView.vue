<script setup>
import { onMounted } from 'vue'
import PostCard from '@/components/feed/PostCard.vue'
import { useFeed } from '@/composables/useFeed'
import { extractErrorMessage } from '@/services/api'
import { ref } from 'vue'

const feedbackMessage = ref('')
const loadError = ref('')

const {
  savedPosts,
  savedHasNext,
  savedLoaded,
  savedLoading,
  fetchSaved,
  toggleLike,
  toggleSave,
  addComment,
} = useFeed()

onMounted(async () => {
  try {
    await fetchSaved({ reset: true })
  } catch (error) {
    loadError.value = extractErrorMessage(error, 'Não foi possível carregar os posts salvos.')
  }
})

async function handleToggleLike(postId) {
  const post = savedPosts.value.find((item) => item.id === postId)
  if (!post) {
    return
  }

  try {
    await toggleLike(post)
    feedbackMessage.value = post.likedByMe ? 'Curtida removida.' : 'Post curtido.'
  } catch (error) {
    feedbackMessage.value = extractErrorMessage(error, 'Não foi possível atualizar a curtida.')
  }
}

async function handleToggleSave(postId) {
  const post = savedPosts.value.find((item) => item.id === postId)
  if (!post) {
    return
  }

  try {
    await toggleSave(post)
    feedbackMessage.value = 'Post removido dos salvos.'
  } catch (error) {
    feedbackMessage.value = extractErrorMessage(error, 'Não foi possível remover o post dos salvos.')
  }
}

async function handleSubmitComment(payload) {
  try {
    await addComment(payload.postId, payload.text)
    feedbackMessage.value = 'Comentário enviado ao post.'
  } catch (error) {
    feedbackMessage.value = extractErrorMessage(error, 'Não foi possível enviar o comentário.')
  }
}

async function handleLoadMore() {
  try {
    await fetchSaved({ reset: false })
  } catch (error) {
    feedbackMessage.value = extractErrorMessage(error, 'Não foi possível carregar mais posts.')
  }
}
</script>

<template>
  <section class="saved-view">
    <h2 class="saved-view__title">Posts salvos</h2>

    <p v-if="loadError" class="saved-view__feedback is-error" role="alert">
      {{ loadError }}
    </p>

    <p v-if="feedbackMessage" class="saved-view__feedback" role="status">
      {{ feedbackMessage }}
    </p>

    <section v-if="savedPosts.length > 0" class="saved-view__stack" aria-label="Posts salvos">
      <PostCard
        v-for="post in savedPosts"
        :key="post.id"
        :post="post"
        @toggle-like="handleToggleLike"
        @toggle-save="handleToggleSave"
        @submit-comment="handleSubmitComment"
      />
    </section>

    <section v-else-if="savedLoaded && !savedLoading" class="saved-view__empty card border-0">
      <h3>Nenhum post salvo</h3>
      <p>
        Salve posts para acessá-los aqui quando quiser.
      </p>
    </section>

    <section v-else-if="savedLoading && savedPosts.length === 0" class="saved-view__empty card border-0">
      <h3>Carregando posts salvos...</h3>
      <p>Buscando as publicações que você salvou.</p>
    </section>

    <div v-if="savedHasNext" class="saved-view__pagination">
      <button class="saved-view__more" type="button" :disabled="savedLoading" @click="handleLoadMore">
        {{ savedLoading ? 'Carregando...' : 'Mostrar mais posts' }}
      </button>
    </div>
  </section>
</template>

<style scoped>
.saved-view,
.saved-view__stack {
  display: grid;
  gap: 1rem;
}

.saved-view__title {
  margin: 0;
  font-size: clamp(1.4rem, 4vw, 2rem);
  font-weight: 800;
}

.saved-view__feedback {
  margin: 0;
  padding: 0.85rem 1rem;
  border: 1px solid var(--app-border);
  border-radius: 0.85rem;
  color: var(--app-text);
  font-weight: 600;
  background: var(--app-surface-soft);
}

.saved-view__feedback.is-error {
  color: #ffb4ba;
  border-color: rgba(255, 48, 64, 0.28);
}

.saved-view__empty {
  padding: 1.5rem;
  border-radius: 1rem;
  background: var(--app-surface);
}

.saved-view__empty h3 {
  margin: 0 0 0.4rem;
  font-size: clamp(1.35rem, 4vw, 1.9rem);
  font-weight: 700;
}

.saved-view__empty p {
  margin: 0;
  color: var(--app-muted);
  line-height: 1.7;
}

.saved-view__pagination {
  display: flex;
  justify-content: center;
  padding: 0.25rem 0 1.5rem;
}

.saved-view__more {
  min-width: 14rem;
  padding: 0.8rem 1.1rem;
  border: 1px solid var(--app-border);
  border-radius: 0.8rem;
  color: var(--app-text);
  font-weight: 700;
  background: var(--app-surface-soft);
  transition:
    background-color 180ms ease,
    border-color 180ms ease,
    color 180ms ease;
}

.saved-view__more:hover:not(:disabled),
.saved-view__more:focus-visible {
  border-color: var(--app-border-strong);
  background: #1b1b1b;
}

.saved-view__more:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>
