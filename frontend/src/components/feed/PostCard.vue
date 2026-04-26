<script setup>
import { computed, ref } from 'vue'
import { RouterLink } from 'vue-router'
import AppIcon from '@/components/layout/AppIcon.vue'
import ProfileAvatar from '@/components/profile/ProfileAvatar.vue'
import MediaDisplay from '@/components/shared/MediaDisplay.vue'
import { useAuth } from '@/composables/useAuth'

const props = defineProps({
  post: {
    type: Object,
    required: true,
  },
})

const emit = defineEmits(['toggle-like', 'toggle-save', 'toggle-repost', 'submit-comment'])

const { currentUser } = useAuth()

const isOwnPost = computed(
  () => Boolean(currentUser.value?.id && props.post.author?.id === currentUser.value.id),
)

const commentText = ref('')

const postLink = computed(() => ({
  name: 'post-detalhes',
  params: {
    postId: props.post.id,
  },
}))

const authorLink = computed(() => ({
  name: 'perfil',
  query: {
    user: props.post.author.username,
  },
}))

const likeLabel = computed(() => {
  const total = props.post.likesCount ?? 0
  return `${total} ${total === 1 ? 'curtida' : 'curtidas'}`
})

const commentLabel = computed(() => {
  const total = props.post.commentsCount ?? 0
  return total > 0
    ? `Ver todos os ${total} ${total === 1 ? 'comentário' : 'comentários'}`
    : 'Seja o primeiro a comentar'
})

const publishedLabel = computed(() => {
  if (!props.post.createdAt) {
    return ''
  }
  return new Intl.DateTimeFormat('pt-BR', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  }).format(new Date(props.post.createdAt))
})

const shortPublishedLabel = computed(() => {
  if (!props.post.createdAt) {
    return ''
  }

  const diffMs = Math.max(0, Date.now() - new Date(props.post.createdAt).getTime())
  const minute = 60 * 1000
  const hour = 60 * minute
  const day = 24 * hour
  const week = 7 * day

  if (diffMs < hour) {
    return `${Math.max(1, Math.floor(diffMs / minute))} min`
  }

  if (diffMs < day) {
    return `${Math.floor(diffMs / hour)} h`
  }

  if (diffMs < week) {
    return `${Math.floor(diffMs / day)} d`
  }

  return new Intl.DateTimeFormat('pt-BR', {
    day: '2-digit',
    month: 'short',
  }).format(new Date(props.post.createdAt))
})

const trimmedComment = computed(() => commentText.value.trim())
const canLikePost = computed(() => !isOwnPost.value)

function handleCommentSubmit() {
  if (!trimmedComment.value) {
    return
  }

  emit('submit-comment', {
    postId: props.post.id,
    text: trimmedComment.value,
  })

  commentText.value = ''
}
</script>

<template>
  <article class="feed-post">
    <header class="feed-post__header">
      <RouterLink :to="authorLink" class="feed-post__author">
        <ProfileAvatar
          :name="post.author.name"
          :username="post.author.username"
          :avatar-url="post.author.avatarUrl"
          :colors="post.author.colors"
          size="sm"
        />

        <span class="feed-post__author-meta">
          <strong>{{ post.author.username }}</strong>
          <span>{{ post.author.name }}</span>
        </span>
      </RouterLink>

      <div class="feed-post__header-side">
        <time class="feed-post__date" :datetime="post.createdAt">{{ shortPublishedLabel }}</time>
        <button class="feed-post__menu" type="button" aria-label="Mais opções">
          <AppIcon name="more" />
        </button>
      </div>
    </header>

    <RouterLink :to="postLink" class="feed-post__media-link">
      <MediaDisplay
        class="feed-post__media"
        :src="post.imageUrl"
        :alt="post.imageAlt"
        :is-video="post.isVideo"
        :autoplay="post.isVideo"
        :muted="true"
        :controls="true"
        :loop="true"
      />
    </RouterLink>

    <div class="feed-post__body">
      <div class="feed-post__toolbar">
        <div class="feed-post__toolbar-group">
          <button
            class="feed-post__icon-button"
            :class="{ 'is-active': post.likedByMe }"
            type="button"
            :disabled="!canLikePost"
            :aria-label="post.likedByMe ? 'Remover curtida' : 'Curtir post'"
            @click="emit('toggle-like', post.id)"
          >
            <AppIcon name="heart" />
          </button>

          <RouterLink :to="postLink" class="feed-post__icon-button" aria-label="Abrir comentários">
            <AppIcon name="comment" />
          </RouterLink>

          <RouterLink :to="postLink" class="feed-post__icon-button" aria-label="Abrir detalhes do post">
            <AppIcon name="share" />
          </RouterLink>

          <button
            v-if="!isOwnPost"
            class="feed-post__icon-button"
            :class="{ 'is-reposted': post.repostedByMe }"
            type="button"
            :aria-label="post.repostedByMe ? 'Remover republicação' : 'Republicar'"
            @click="emit('toggle-repost', post.id)"
          >
            <AppIcon name="repost" />
          </button>
        </div>

        <button
          class="feed-post__icon-button"
          :class="{ 'is-saved': post.savedByMe }"
          type="button"
          :aria-label="post.savedByMe ? 'Remover dos salvos' : 'Salvar post'"
          @click="emit('toggle-save', post.id)"
        >
          <AppIcon name="save" />
        </button>
      </div>

      <p class="feed-post__likes">{{ likeLabel }}</p>

      <p v-if="post.caption" class="feed-post__caption">
        <RouterLink :to="authorLink" class="feed-post__caption-link">
          {{ post.author.username }}
        </RouterLink>
        {{ post.caption }}
      </p>

      <RouterLink v-if="post.commentsCount > 0" :to="postLink" class="feed-post__meta-link">
        {{ commentLabel }}
      </RouterLink>
      <p v-else class="feed-post__meta-link">{{ commentLabel }}</p>

      <time class="feed-post__timestamp" :datetime="post.createdAt">{{ publishedLabel }}</time>

      <form class="feed-post__comment-form" @submit.prevent="handleCommentSubmit">
        <input
          v-model="commentText"
          class="feed-post__comment-input"
          type="text"
          maxlength="2200"
          placeholder="Adicione um comentário..."
        />
        <button class="feed-post__submit" type="submit" :disabled="!trimmedComment">
          Publicar
        </button>
      </form>
    </div>
  </article>
</template>

<style scoped>
.feed-post {
  overflow: hidden;
  border-radius: 0;
  background: transparent;
  border-bottom: 1px solid var(--app-border);
  padding-bottom: 0.5rem;
}

.feed-post__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.85rem;
  padding: 0.85rem 0.25rem 0.75rem;
}

.feed-post__author {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  min-width: 0;
  color: inherit;
  text-decoration: none;
}

.feed-post__author-meta {
  display: grid;
  min-width: 0;
}

.feed-post__author-meta strong {
  color: var(--app-text);
  font-size: 0.93rem;
  font-weight: 600;
}

.feed-post__author-meta span,
.feed-post__date,
.feed-post__timestamp {
  color: var(--app-muted);
  font-size: 0.78rem;
}

.feed-post__header-side {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.feed-post__menu {
  display: grid;
  place-items: center;
  width: 2rem;
  height: 2rem;
  padding: 0;
  border: 0;
  color: var(--app-text);
  background: none;
}

.feed-post__media-link {
  display: block;
  border-radius: 0.1rem;
  overflow: hidden;
}

.feed-post__media {
  display: block;
  width: 100%;
  aspect-ratio: 1 / 1;
  object-fit: cover;
  background: var(--app-surface-soft);
}

.feed-post__body {
  padding: 0 0.25rem;
}

.feed-post__toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  margin-bottom: 0.5rem;
  padding-top: 0.1rem;
}

.feed-post__toolbar-group {
  display: flex;
  align-items: center;
  gap: 0.05rem;
}

.feed-post__icon-button {
  display: grid;
  place-items: center;
  width: 2.4rem;
  height: 2.4rem;
  padding: 0;
  border: 0;
  color: var(--app-text);
  background: none;
  text-decoration: none;
  transition: transform 120ms ease, color 160ms ease;
}

.feed-post__icon-button:hover,
.feed-post__icon-button:focus-visible,
.feed-post__menu:hover,
.feed-post__menu:focus-visible {
  color: var(--app-muted);
}

.feed-post__icon-button.is-active {
  color: var(--app-danger);
}

.feed-post__icon-button.is-active:hover {
  color: var(--app-danger);
}

.feed-post__icon-button.is-saved {
  color: #ffd60a;
}

.feed-post__icon-button.is-reposted {
  color: #3cc663;
}

.feed-post__icon-button:disabled {
  opacity: 0.35;
  cursor: not-allowed;
}

.feed-post__likes {
  margin: 0 0 0.45rem;
  color: var(--app-text);
  font-size: 0.92rem;
  font-weight: 700;
}

.feed-post__caption {
  margin: 0 0 0.4rem;
  color: var(--app-text);
  line-height: 1.6;
  white-space: pre-line;
  font-size: 0.92rem;
}

.feed-post__caption-link {
  margin-right: 0.3rem;
  color: var(--app-text);
  font-weight: 700;
  text-decoration: none;
}

.feed-post__meta-link {
  display: block;
  margin: 0 0 0.35rem;
  color: var(--app-muted);
  font-size: 0.86rem;
  text-decoration: none;
}

.feed-post__timestamp {
  display: block;
  margin-bottom: 0.6rem;
  color: var(--app-muted);
  font-size: 0.72rem;
  letter-spacing: 0.07em;
  text-transform: uppercase;
}

.feed-post__comment-form {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.75rem;
  padding: 0.7rem 0;
  border-top: 1px solid var(--app-border);
}

.feed-post__comment-input {
  flex: 1;
  min-width: 0;
  padding: 0;
  border: 0;
  color: var(--app-text);
  font-size: 0.9rem;
  background: transparent;
}

.feed-post__comment-input::placeholder {
  color: var(--app-muted);
}

.feed-post__comment-input:focus-visible {
  outline: none;
}

.feed-post__submit {
  padding: 0;
  border: 0;
  color: var(--app-link);
  background: none;
  font-size: 0.88rem;
  font-weight: 700;
}

.feed-post__submit:disabled {
  cursor: not-allowed;
  opacity: 0.35;
}
</style>
