<script setup>
import { computed, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import AppIcon from '@/components/layout/AppIcon.vue'
import ProfileAvatar from '@/components/profile/ProfileAvatar.vue'
import MediaDisplay from '@/components/shared/MediaDisplay.vue'
import { useAuth } from '@/composables/useAuth'
import { extractErrorMessage } from '@/services/api'
import * as followsService from '@/services/follows.service'
import * as usersService from '@/services/users.service'
import * as repostsService from '@/services/reposts.service'
import { normalizePost } from '@/stores/feed'
import { normalizeUser } from '@/stores/profileUtils'

const route = useRoute()
const { currentUser } = useAuth()

const profile = ref(null)
const postsList = ref([])
const postsCount = ref(0)
const followersList = ref([])
const followersCount = ref(0)
const followingList = ref([])
const followingCount = ref(0)
const isFollowedByViewer = ref(false)
const isFollowPending = ref(false)
const isLoading = ref(false)
const loadError = ref('')
const feedbackMessage = ref('')
const followPending = ref(false)

const activeTab = ref('posts')
const repostsList = ref([])
const repostsLoaded = ref(false)
const repostsLoading = ref(false)

const selectedUsername = computed(() =>
  typeof route.query.user === 'string' ? route.query.user.trim().toLowerCase() : '',
)

const isOwnProfile = computed(() => {
  if (!profile.value || !currentUser.value) {
    return false
  }
  return profile.value.id === currentUser.value.id
})

const followButtonLabel = computed(() => {
  if (isFollowedByViewer.value) return 'Seguindo'
  if (isFollowPending.value) return 'Solicitado'
  return 'Seguir'
})

const isPrivateAndHidden = computed(() =>
  profile.value?.isPrivate && !isFollowedByViewer.value && !isOwnProfile.value,
)

const connectionsQuery = computed(() => {
  if (!profile.value || isOwnProfile.value) {
    return {}
  }
  return { user: profile.value.username }
})

const followersPreview = computed(() => followersList.value.slice(0, 4))
const followingPreview = computed(() => followingList.value.slice(0, 4))
const followersRoute = computed(() => ({
  name: 'perfil-lista',
  params: { type: 'seguidores' },
  query: connectionsQuery.value,
}))
const followingRoute = computed(() => ({
  name: 'perfil-lista',
  params: { type: 'seguindo' },
  query: connectionsQuery.value,
}))
const secondaryActionLabel = computed(() =>
  isOwnProfile.value ? 'Ver conexões' : 'Ver seguidores',
)

async function loadReposts() {
  if (!profile.value?.id || repostsLoading.value) return
  repostsLoading.value = true
  try {
    const resp = await usersService.getRepostsByUser(profile.value.id, 15, 1)
    repostsList.value = (resp.data ?? []).map(normalizePost).filter(Boolean)
    repostsLoaded.value = true
  } catch {
    repostsList.value = []
    repostsLoaded.value = true
  } finally {
    repostsLoading.value = false
  }
}

async function switchTab(tab) {
  activeTab.value = tab
  if (tab === 'reposts' && !repostsLoaded.value) {
    await loadReposts()
  }
}

async function loadProfile() {
  isLoading.value = true
  loadError.value = ''
  feedbackMessage.value = ''

  profile.value = null
  postsList.value = []
  postsCount.value = 0
  followersList.value = []
  followersCount.value = 0
  followingList.value = []
  followingCount.value = 0
  isFollowedByViewer.value = false
  isFollowPending.value = false
  activeTab.value = 'posts'
  repostsList.value = []
  repostsLoaded.value = false

  try {
    let targetUser

    if (selectedUsername.value) {
      const raw = await usersService.getByUsername(selectedUsername.value)
      targetUser = normalizeUser(raw)
    } else if (currentUser.value?.username) {
      const raw = await usersService.getByUsername(currentUser.value.username)
      targetUser = normalizeUser(raw)
    }

    if (!targetUser) {
      loadError.value = 'Perfil não encontrado.'
      return
    }

    profile.value = targetUser

    const [postsResp, followersResp, followingResp] = await Promise.all([
      usersService.getPostsByUser(targetUser.id, 9, 1),
      followsService.followers(targetUser.id, 8, 1),
      followsService.following(targetUser.id, 8, 1),
    ])

    postsList.value = (postsResp.data ?? []).map(normalizePost).filter(Boolean)
    postsCount.value = Number(postsResp.total ?? postsList.value.length)

    followersList.value = (followersResp.data ?? []).map(normalizeUser).filter(Boolean)
    followersCount.value = Number(followersResp.total ?? followersList.value.length)

    followingList.value = (followingResp.data ?? []).map(normalizeUser).filter(Boolean)
    followingCount.value = Number(followingResp.total ?? followingList.value.length)

    if (currentUser.value?.id && currentUser.value.id !== targetUser.id) {
      try {
        const result = await followsService.isFollowing(targetUser.id)
        isFollowedByViewer.value = Boolean(result.is_following)
        isFollowPending.value = Boolean(result.is_pending)
      } catch {
        isFollowedByViewer.value = false
        isFollowPending.value = false
      }
    }
  } catch (error) {
    loadError.value = extractErrorMessage(error, 'Não foi possível carregar o perfil.')
  } finally {
    isLoading.value = false
  }
}

async function handleToggleFollow() {
  if (!profile.value || isOwnProfile.value || followPending.value) {
    return
  }

  followPending.value = true

  try {
    if (isFollowedByViewer.value || isFollowPending.value) {
      await followsService.unfollow(profile.value.id)
      const wasFollowing = isFollowedByViewer.value
      isFollowedByViewer.value = false
      isFollowPending.value = false
      if (wasFollowing) {
        followersCount.value = Math.max(0, followersCount.value - 1)
        feedbackMessage.value = `Você deixou de seguir @${profile.value.username}.`
      } else {
        feedbackMessage.value = `Solicitação para @${profile.value.username} cancelada.`
      }
    } else {
      const result = await followsService.follow(profile.value.id)
      if (result.status === 'pending') {
        isFollowPending.value = true
        feedbackMessage.value = `Solicitação enviada para @${profile.value.username}.`
      } else {
        isFollowedByViewer.value = true
        followersCount.value = followersCount.value + 1
        feedbackMessage.value = `Agora você segue @${profile.value.username}.`
      }
    }
  } catch (error) {
    feedbackMessage.value = extractErrorMessage(
      error,
      'Não foi possível atualizar o relacionamento agora.',
    )
  } finally {
    followPending.value = false
  }
}

watch(selectedUsername, loadProfile, { immediate: true })
watch(
  () => currentUser.value?.id,
  () => {
    if (!selectedUsername.value) {
      loadProfile()
    }
  },
)
</script>

<template>
  <section v-if="isLoading && !profile" class="profile-view__state card border-0">
    <p class="mb-0 text-body-secondary">Carregando perfil...</p>
  </section>

  <section v-else-if="loadError" class="profile-view__state card border-0">
    <h2 class="h4 mb-3">Perfil indisponível</h2>
    <p class="text-body-secondary mb-0">{{ loadError }}</p>
  </section>

  <section v-else-if="profile" class="profile-view">
    <p v-if="feedbackMessage" class="profile-view__feedback" role="status">
      {{ feedbackMessage }}
    </p>

    <section class="profile-header">
      <div class="profile-header__avatar">
        <ProfileAvatar
          :name="profile.name"
          :username="profile.username"
          :avatar-url="profile.avatarUrl"
          :colors="profile.colors"
          size="xl"
        />
      </div>

      <div class="profile-header__content">
        <div class="profile-header__top-row">
          <h1 class="profile-header__username">{{ profile.username }}</h1>

          <template v-if="!isOwnProfile">
            <button
              class="ph-btn"
              :class="(isFollowedByViewer || isFollowPending) ? 'ph-btn--secondary' : 'ph-btn--primary'"
              type="button"
              :disabled="followPending"
              @click="handleToggleFollow"
            >
              {{ followButtonLabel }}
            </button>
            <button class="ph-btn ph-btn--secondary" type="button">Mensagem</button>
            <button class="ph-icon-btn" type="button" aria-label="Mais opções">
              <AppIcon name="more" />
            </button>
          </template>
        </div>

        <div class="profile-header__stats">
          <span class="profile-header__stat">
            <strong>{{ postsCount }}</strong> publicações
          </span>
          <RouterLink class="profile-header__stat profile-header__stat--link" :to="followersRoute">
            <strong>{{ followersCount }}</strong> seguidores
          </RouterLink>
          <RouterLink class="profile-header__stat profile-header__stat--link" :to="followingRoute">
            <strong>{{ followingCount }}</strong> seguindo
          </RouterLink>
        </div>

        <div class="profile-header__bio">
          <strong>{{ profile.name }}</strong>
          <p v-if="profile.bio">{{ profile.bio }}</p>
          <p v-else class="profile-header__bio-muted">
            {{ isOwnProfile
              ? 'Adicione uma bio para completar seu perfil.'
              : 'Este perfil ainda não escreveu uma bio.' }}
          </p>
        </div>

        <!-- Own profile action buttons below bio -->
        <div v-if="isOwnProfile" class="profile-header__own-actions">
          <RouterLink class="ph-btn ph-btn--secondary ph-btn--block" :to="{ name: 'perfil-editar' }">
            Editar perfil
          </RouterLink>
          <RouterLink class="ph-btn ph-btn--secondary ph-btn--block" :to="{ name: 'perfil-editar' }">
            Ver Itens Arquivados
          </RouterLink>
        </div>
      </div>
    </section>

    <section v-if="isPrivateAndHidden" class="profile-private card border-0">
      <AppIcon name="lock" />
      <h3>Esta conta é privada</h3>
      <p>Siga {{ profile.username }} para ver as publicações.</p>
    </section>

    <nav v-if="!isPrivateAndHidden" class="profile-tabs" aria-label="Seções do perfil">
      <button
        class="profile-tabs__item"
        :class="{ 'is-active': activeTab === 'posts' }"
        type="button"
        @click="switchTab('posts')"
      >
        <AppIcon name="grid" />
        <span>Publicações</span>
      </button>

      <button
        class="profile-tabs__item"
        :class="{ 'is-active': activeTab === 'reposts' }"
        type="button"
        @click="switchTab('reposts')"
      >
        <AppIcon name="repost" />
        <span>Republicações</span>
      </button>

    </nav>

    <!-- Posts grid -->
    <template v-if="!isPrivateAndHidden && activeTab === 'posts'">
      <section v-if="postsList.length > 0" class="profile-grid">
        <RouterLink
          v-for="(post, idx) in postsList"
          :key="post.id"
          :to="{ name: 'post-detalhes', params: { postId: post.id }, query: { ids: postsList.map(p => p.id).join(','), idx } }"
          class="profile-grid__item"
        >
          <MediaDisplay :src="post.imageUrl" :alt="post.imageAlt" :is-video="post.isVideo" :thumbnail="true" />
          <div class="profile-grid__overlay">
            <span>{{ post.likesCount }} curtidas</span>
            <span>{{ post.commentsCount }} comentários</span>
          </div>
        </RouterLink>
      </section>

      <section v-else class="profile-empty card border-0">
        <h3>Nenhum post por aqui ainda</h3>
        <p>
          {{ isOwnProfile
            ? 'Publique algo para preencher sua grade e mostrar atividade no perfil.'
            : 'Quando este usuário publicar, a grade começa a aparecer aqui.' }}
        </p>
        <RouterLink
          v-if="isOwnProfile"
          class="btn btn-primary align-self-start"
          :to="{ name: 'criar' }"
        >
          Criar primeiro post
        </RouterLink>
      </section>
    </template>

    <!-- Reposts grid -->
    <template v-if="!isPrivateAndHidden && activeTab === 'reposts'">
      <div v-if="repostsLoading" class="profile-grid">
        <div v-for="n in 6" :key="n" class="profile-grid__skeleton" />
      </div>

      <section v-else-if="repostsList.length > 0" class="profile-grid">
        <RouterLink
          v-for="(post, idx) in repostsList"
          :key="post.id"
          :to="{ name: 'post-detalhes', params: { postId: post.id }, query: { ids: repostsList.map(p => p.id).join(','), idx } }"
          class="profile-grid__item"
        >
          <MediaDisplay :src="post.imageUrl" :alt="post.imageAlt" :is-video="post.isVideo" :thumbnail="true" />
          <div class="profile-grid__overlay">
            <span>@{{ post.author.username }}</span>
            <span>{{ post.likesCount }} curtidas</span>
          </div>
        </RouterLink>
      </section>

      <section v-else class="profile-empty card border-0">
        <h3>Nenhuma republicação ainda</h3>
        <p>
          {{ isOwnProfile
            ? 'Posts que você republicar de outros usuários aparecerão aqui.'
            : 'Quando este usuário republicar algo, aparecerá aqui.' }}
        </p>
      </section>
    </template>
  </section>
</template>

<style scoped>
.profile-view {
  display: grid;
  gap: 1.5rem;
}

.profile-view__state,
.profile-empty {
  padding: 1.5rem;
  border-radius: 1rem;
  background: var(--app-surface);
}

.profile-view__feedback {
  margin: 0;
  padding: 0.85rem 1rem;
  border: 1px solid var(--app-border);
  border-radius: 0.85rem;
  color: var(--app-text);
  font-weight: 600;
  background: var(--app-surface-soft);
}

.profile-header {
  display: flex;
  gap: 0;
  padding: 1.25rem 0 2rem;
  border-bottom: 1px solid var(--app-border);
  align-items: flex-start;
}

.profile-header__avatar {
  flex: 0 0 280px;
  display: flex;
  align-items: center;
  justify-content: center;
  align-self: center;
}

.profile-header__content {
  flex: 1;
  min-width: 0;
  display: grid;
  gap: 1rem;
}

.profile-header__top-row {
  display: flex;
  align-items: center;
  gap: 1rem;
  flex-wrap: wrap;
}

.profile-header__username {
  margin: 0;
  font-size: 1.4rem;
  font-weight: 400;
  color: var(--app-text);
}

.profile-header__actions {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
}

/* Action buttons */
.ph-btn {
  padding: 0.42rem 1rem;
  border-radius: 0.55rem;
  font-size: 0.88rem;
  font-weight: 600;
  border: 0;
  cursor: pointer;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  transition: opacity 150ms ease;
}

.ph-btn:hover { opacity: 0.85; }

.ph-btn--primary {
  background: var(--app-accent);
  color: #fff;
}

.ph-btn--secondary {
  background: var(--app-surface-soft);
  color: var(--app-text);
  border: 1px solid var(--app-border);
}

.ph-btn--block {
  flex: 1;
  justify-content: center;
}

.profile-header__own-actions {
  display: flex;
  gap: 0.6rem;
}

.ph-icon-btn {
  display: grid;
  place-items: center;
  width: 2.2rem;
  height: 2.2rem;
  border-radius: 50%;
  border: 0;
  background: none;
  color: var(--app-text);
  cursor: pointer;
  text-decoration: none;
  transition: background 150ms ease;
}

.ph-icon-btn:hover {
  background: var(--app-surface-soft);
}

.ph-icon-btn .app-icon {
  width: 1.35rem;
  height: 1.35rem;
}

/* Stats */
.profile-header__stats {
  display: flex;
  align-items: center;
  gap: 2.25rem;
  font-size: 1rem;
}

.profile-header__stat {
  color: var(--app-text);
}

.profile-header__stat strong {
  font-weight: 700;
}

.profile-header__stat--link {
  text-decoration: none;
  color: var(--app-text);
  transition: color 150ms ease;
}

.profile-header__stat--link:hover {
  color: var(--app-muted);
}

/* Bio */
.profile-header__bio {
  display: grid;
  gap: 0.2rem;
  font-size: 0.9rem;
  line-height: 1.5;
}

.profile-header__bio strong {
  color: var(--app-text);
  font-weight: 600;
}

.profile-header__bio p,
.profile-header__bio-muted,
.profile-empty p {
  margin: 0;
  color: var(--app-muted);
  white-space: pre-line;
}

.profile-tabs {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 3.75rem;
  border-top: 1px solid var(--app-border);
}

.profile-tabs__item {
  display: inline-flex;
  align-items: center;
  gap: 0.55rem;
  padding-bottom: 1rem;
  margin-bottom: -1px;
  border: 0;
  border-bottom: 1.5px solid transparent;
  color: var(--app-muted);
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.12em;
  text-decoration: none;
  text-transform: uppercase;
  background: none;
  cursor: pointer;
  padding-top: 0.75rem;
}

.profile-tabs__item.is-active {
  border-bottom-color: var(--app-text);
  color: var(--app-text);
}

.profile-grid {
  display: grid;
  gap: 3px;
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

.profile-grid__item {
  position: relative;
  display: block;
  overflow: hidden;
  aspect-ratio: 1 / 1;
  color: inherit;
  text-decoration: none;
  background: #111;
}

.profile-grid__item img {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: contain;
}

.profile-grid__overlay {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 1.2rem;
  padding: 1rem;
  color: #fff;
  font-size: 0.92rem;
  font-weight: 700;
  text-align: center;
  background: linear-gradient(180deg, rgba(0, 0, 0, 0.08) 0%, rgba(0, 0, 0, 0.74) 100%);
  opacity: 0;
  transition: opacity 180ms ease;
}

.profile-grid__item:hover .profile-grid__overlay,
.profile-grid__item:focus-visible .profile-grid__overlay {
  opacity: 1;
}

.profile-empty {
  display: grid;
  gap: 0.8rem;
}

.profile-empty h3 {
  margin: 0;
  font-size: 1.35rem;
  font-weight: 700;
}

.profile-empty p {
  margin: 0;
}

.profile-private {
  display: grid;
  gap: 0.6rem;
  padding: 2.5rem 1.5rem;
  border-radius: 1rem;
  background: var(--app-surface);
  text-align: center;
  color: var(--app-muted);
}

.profile-private svg {
  width: 2.5rem;
  height: 2.5rem;
  margin: 0 auto;
  opacity: 0.5;
}

.profile-private h3 {
  margin: 0;
  font-size: 1.15rem;
  font-weight: 700;
  color: var(--app-text);
}

.profile-private p {
  margin: 0;
  font-size: 0.9rem;
  line-height: 1.6;
}

.profile-grid__skeleton {
  aspect-ratio: 1 / 1;
  background: var(--app-surface-soft);
  animation: profile-pulse 1.4s ease-in-out infinite;
}

@keyframes profile-pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.45; }
}

@media (max-width: 767.98px) {
  .profile-header {
    flex-direction: column;
    align-items: center;
    text-align: center;
  }

  .profile-header__avatar {
    flex: none;
    width: auto;
  }

  .profile-header__stats {
    gap: 1.25rem;
    font-size: 0.9rem;
    justify-content: center;
  }

  .profile-header__top-row {
    justify-content: center;
  }

  .profile-tabs {
    gap: 1.5rem;
    overflow-x: auto;
    justify-content: center;
  }

  .profile-grid__overlay {
    opacity: 1;
    align-items: flex-end;
    justify-content: space-between;
    gap: 0.75rem;
    font-size: 0.78rem;
  }
}
</style>
