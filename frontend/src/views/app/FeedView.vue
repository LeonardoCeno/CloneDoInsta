<script>
export default { name: 'FeedView' }
</script>

<script setup>
import { onMounted, onActivated, ref } from 'vue'
import PostCard from '@/components/feed/PostCard.vue'
import StoriesBar from '@/components/stories/StoriesBar.vue'
import StoryViewer from '@/components/stories/StoryViewer.vue'
import { useFeed } from '@/composables/useFeed'
import { extractErrorMessage } from '@/services/api'
import { useStoriesStore } from '@/stores/stories'
import * as repostsService from '@/services/reposts.service'
import { useToastStore } from '@/stores/toast'

const storiesStore = useStoriesStore()
const toastStore = useToastStore()

const feedbackMessage = ref('')
const loadError = ref('')

const {
  feedPosts,
  feedHasNext,
  feedLoaded,
  feedLoading,
  fetchFeed,
  loadMoreFeed,
  toggleLike,
  toggleSave,
  addComment,
} = useFeed()

async function initFeed() {
  if (feedPosts.value.length > 0) return
  try {
    await Promise.all([
      fetchFeed({ reset: true }),
      storiesStore.fetchFeed(),
    ])
  } catch (error) {
    loadError.value = extractErrorMessage(error, 'Não foi possível carregar o feed agora.')
  }
}

onMounted(initFeed)
onActivated(initFeed)

async function handleToggleLike(postId) {
  const post = feedPosts.value.find((item) => item.id === postId)
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
  const post = feedPosts.value.find((item) => item.id === postId)
  if (!post) {
    return
  }

  try {
    await toggleSave(post)
    toastStore.show(post.savedByMe ? 'Post removido dos salvos.' : 'Post salvo.', 'info')
  } catch (error) {
    toastStore.show(extractErrorMessage(error, 'Não foi possível salvar o post.'), 'error')
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

async function handleToggleRepost(postId) {
  const post = feedPosts.value.find((item) => item.id === postId)
  if (!post) return

  const wasReposted = post.repostedByMe
  post.repostedByMe = !wasReposted
  post.repostsCount = wasReposted ? Math.max(0, post.repostsCount - 1) : post.repostsCount + 1

  try {
    if (wasReposted) {
      await repostsService.unrepost(post.id)
      toastStore.show('Republicação removida.', 'info')
    } else {
      await repostsService.repost(post.id)
      toastStore.show('Post republicado.', 'success')
    }
  } catch (error) {
    post.repostedByMe = wasReposted
    post.repostsCount = wasReposted ? post.repostsCount + 1 : Math.max(0, post.repostsCount - 1)
    toastStore.show(
      extractErrorMessage(error, 'Não foi possível republicar o post.'),
      'error',
    )
  }
}

async function handleLoadMore() {
  try {
    await loadMoreFeed()
  } catch (error) {
    feedbackMessage.value = extractErrorMessage(error, 'Não foi possível carregar mais posts.')
  }
}
</script>

<template>
  <section class="feed-view">
    <StoriesBar v-if="storiesStore.loaded || storiesStore.loading" />

    <StoryViewer />
    <p v-if="loadError" class="feed-view__feedback is-error" role="alert">
      {{ loadError }}
    </p>

    <p v-if="feedbackMessage" class="feed-view__feedback" role="status">
      {{ feedbackMessage }}
    </p>

    <section v-if="feedPosts.length > 0" class="feed-view__stack" aria-label="Lista de posts">
      <PostCard
        v-for="post in feedPosts"
        :key="post.id"
        :post="post"
        @toggle-like="handleToggleLike"
        @toggle-save="handleToggleSave"
        @toggle-repost="handleToggleRepost"
        @submit-comment="handleSubmitComment"
      />
    </section>

    <section v-else-if="feedLoaded && !feedLoading" class="feed-view__empty card border-0">
      <h3>Nenhum post para mostrar</h3>
      <p>
        Assim que você seguir perfis ou publicar algo, o feed passa a aparecer aqui com a mesma
        estrutura de interação.
      </p>
    </section>

    <section v-else-if="feedLoading && feedPosts.length === 0" class="feed-view__empty card border-0">
      <h3>Carregando feed...</h3>
      <p>Buscando as publicações mais recentes dos perfis que você acompanha.</p>
    </section>

    <div v-if="feedHasNext" class="feed-view__pagination">
      <button class="feed-view__more" type="button" :disabled="feedLoading" @click="handleLoadMore">
        {{ feedLoading ? 'Carregando...' : 'Mostrar mais posts' }}
      </button>
    </div>
  </section>
</template>

<style scoped>
.feed-view {
  display: grid;
  gap: 0;
}

.feed-view__stack {
  display: grid;
  gap: 0;
}

.feed-view__feedback {
  margin: 0;
  padding: 0.85rem 1rem;
  border: 1px solid var(--app-border);
  border-radius: 0.85rem;
  color: var(--app-text);
  font-weight: 600;
  background: var(--app-surface-soft);
}

.feed-view__feedback.is-error {
  color: #ffb4ba;
  border-color: rgba(255, 48, 64, 0.28);
}

.feed-view__empty {
  padding: 2rem 0.5rem;
}

.feed-view__empty h3 {
  margin: 0 0 0.4rem;
  font-size: clamp(1.35rem, 4vw, 1.9rem);
  font-weight: 700;
}

.feed-view__empty p {
  margin: 0;
  color: var(--app-muted);
  line-height: 1.7;
}

.feed-view__pagination {
  display: flex;
  justify-content: center;
  padding: 0.25rem 0 1.5rem;
}

.feed-view__more {
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

.feed-view__more:hover:not(:disabled),
.feed-view__more:focus-visible {
  border-color: var(--app-border-strong);
  background: #1b1b1b;
}

.feed-view__more:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>
