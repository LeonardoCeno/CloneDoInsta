<script setup>
import { computed, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import AppIcon from '@/components/layout/AppIcon.vue'
import ProfileAvatar from '@/components/profile/ProfileAvatar.vue'
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

const followButtonLabel = computed(() =>
  isFollowedByViewer.value ? 'Deixar de seguir' : 'Seguir perfil',
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
      } catch {
        isFollowedByViewer.value = false
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
    if (isFollowedByViewer.value) {
      await followsService.unfollow(profile.value.id)
      isFollowedByViewer.value = false
      followersCount.value = Math.max(0, followersCount.value - 1)
      feedbackMessage.value = `Você deixou de seguir @${profile.value.username}.`
    } else {
      await followsService.follow(profile.value.id)
      isFollowedByViewer.value = true
      followersCount.value = followersCount.value + 1
      feedbackMessage.value = `Agora você segue @${profile.value.username}.`
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
        <div class="profile-header__identity">
          <div class="profile-header__title-row">
            <h1>{{ profile.username }}</h1>

            <RouterLink
              v-if="isOwnProfile"
              class="profile-header__settings"
              :to="{ name: 'perfil-editar' }"
              aria-label="Editar configurações do perfil"
            >
              <AppIcon name="settings" />
            </RouterLink>
          </div>

          <div class="profile-header__actions">
            <RouterLink
              v-if="isOwnProfile"
              class="btn btn-outline-secondary"
              :to="{ name: 'perfil-editar' }"
            >
              Editar perfil
            </RouterLink>

            <button
              v-else
              class="btn btn-outline-secondary"
              type="button"
              :disabled="followPending"
              @click="handleToggleFollow"
            >
              {{ followButtonLabel }}
            </button>

            <RouterLink class="btn btn-outline-secondary" :to="followersRoute">
              {{ secondaryActionLabel }}
            </RouterLink>
          </div>
        </div>

        <div class="profile-header__stats">
          <article>
            <strong>{{ postsCount }}</strong>
            <span>publicações</span>
          </article>

          <RouterLink class="profile-header__stat-link" :to="followersRoute">
            <strong>{{ followersCount }}</strong>
            <span>seguidores</span>
          </RouterLink>

          <RouterLink class="profile-header__stat-link" :to="followingRoute">
            <strong>{{ followingCount }}</strong>
            <span>seguindo</span>
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
      </div>
    </section>

    <section class="profile-summary">
      <RouterLink class="profile-summary__card" :to="followersRoute">
        <div class="profile-summary__avatars">
          <ProfileAvatar
            v-for="account in followersPreview"
            :key="`followers-${account.id}`"
            :name="account.name"
            :username="account.username"
            :avatar-url="account.avatarUrl"
            :colors="account.colors"
            size="sm"
            class="profile-summary__avatar"
          />
          <span v-if="followersPreview.length === 0" class="profile-summary__placeholder">
            <AppIcon name="profile" />
          </span>
        </div>

        <div class="profile-summary__copy">
          <span>Seguidores</span>
          <strong>{{ followersCount }} pessoas acompanham este perfil</strong>
        </div>
      </RouterLink>

      <RouterLink class="profile-summary__card" :to="followingRoute">
        <div class="profile-summary__avatars">
          <ProfileAvatar
            v-for="account in followingPreview"
            :key="`following-${account.id}`"
            :name="account.name"
            :username="account.username"
            :avatar-url="account.avatarUrl"
            :colors="account.colors"
            size="sm"
            class="profile-summary__avatar"
          />
          <span v-if="followingPreview.length === 0" class="profile-summary__placeholder">
            <AppIcon name="discover" />
          </span>
        </div>

        <div class="profile-summary__copy">
          <span>Seguindo</span>
          <strong>{{ followingCount }} contas no radar</strong>
        </div>
      </RouterLink>
    </section>

    <nav class="profile-tabs" aria-label="Seções do perfil">
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

      <RouterLink class="profile-tabs__item" :to="followersRoute">
        <AppIcon name="profile" />
        <span>Seguidores</span>
      </RouterLink>

      <RouterLink class="profile-tabs__item" :to="followingRoute">
        <AppIcon name="discover" />
        <span>Seguindo</span>
      </RouterLink>
    </nav>

    <!-- Posts grid -->
    <template v-if="activeTab === 'posts'">
      <section v-if="postsList.length > 0" class="profile-grid">
        <RouterLink
          v-for="post in postsList"
          :key="post.id"
          :to="{ name: 'post-detalhes', params: { postId: post.id } }"
          class="profile-grid__item"
        >
          <img :src="post.imageUrl" :alt="post.imageAlt" loading="lazy" />
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
    <template v-if="activeTab === 'reposts'">
      <div v-if="repostsLoading" class="profile-grid">
        <div v-for="n in 6" :key="n" class="profile-grid__skeleton" />
      </div>

      <section v-else-if="repostsList.length > 0" class="profile-grid">
        <RouterLink
          v-for="post in repostsList"
          :key="post.id"
          :to="{ name: 'post-detalhes', params: { postId: post.id } }"
          class="profile-grid__item"
        >
          <img :src="post.imageUrl" :alt="post.imageAlt" loading="lazy" />
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
  display: grid;
  gap: 1.5rem;
  padding-bottom: 2rem;
  border-bottom: 1px solid var(--app-border);
}

.profile-header__avatar {
  display: flex;
  justify-content: center;
}

.profile-header__content,
.profile-header__identity,
.profile-header__bio,
.profile-summary__copy {
  display: grid;
  gap: 0.45rem;
}

.profile-header__title-row,
.profile-header__actions {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex-wrap: wrap;
}

.profile-header__title-row h1 {
  margin: 0;
  color: var(--app-text);
  font-size: clamp(1.7rem, 4vw, 2.1rem);
  font-weight: 600;
}

.profile-header__settings {
  display: grid;
  place-items: center;
  width: 2.4rem;
  height: 2.4rem;
  border: 1px solid var(--app-border);
  border-radius: 999px;
  color: var(--app-text);
  background: var(--app-surface-soft);
  text-decoration: none;
}

.profile-header__stats {
  display: flex;
  flex-wrap: wrap;
  gap: 1.5rem;
}

.profile-header__stats article,
.profile-header__stat-link {
  display: grid;
  gap: 0.2rem;
  color: inherit;
  text-decoration: none;
}

.profile-header__stats strong {
  color: var(--app-text);
  font-size: 1.12rem;
}

.profile-header__stats span,
.profile-header__bio p,
.profile-header__bio-muted,
.profile-summary__copy span,
.profile-empty p {
  color: var(--app-muted);
  line-height: 1.7;
}

.profile-header__bio strong {
  color: var(--app-text);
  font-size: 0.98rem;
}

.profile-summary {
  display: grid;
  gap: 0.85rem;
}

.profile-summary__card {
  display: flex;
  align-items: center;
  gap: 0.9rem;
  padding: 1rem;
  border: 1px solid var(--app-border);
  border-radius: 1rem;
  color: inherit;
  text-decoration: none;
  background: var(--app-surface);
}

.profile-summary__avatars {
  display: flex;
  align-items: center;
  min-width: 7rem;
}

.profile-summary__avatar + .profile-summary__avatar {
  margin-left: -0.55rem;
}

.profile-summary__placeholder {
  display: grid;
  place-items: center;
  width: 2.2rem;
  height: 2.2rem;
  border: 1px solid var(--app-border);
  border-radius: 50%;
  color: var(--app-muted);
  background: var(--app-surface-soft);
}

.profile-summary__copy strong {
  color: var(--app-text);
  font-size: 0.95rem;
}

.profile-tabs {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 1.5rem;
  padding-top: 1rem;
  border-top: 1px solid var(--app-border);
}

.profile-tabs__item {
  display: inline-flex;
  align-items: center;
  gap: 0.55rem;
  padding-top: 1rem;
  margin-top: -1rem;
  border: 0;
  border-top: 1px solid transparent;
  color: var(--app-muted);
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.12em;
  text-decoration: none;
  text-transform: uppercase;
  background: none;
  cursor: pointer;
}

.profile-tabs__item.is-active {
  border-top-color: var(--app-text);
  color: var(--app-text);
}

.profile-grid {
  display: grid;
  gap: 0.2rem;
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

.profile-grid__item {
  position: relative;
  display: block;
  overflow: hidden;
  aspect-ratio: 1 / 1;
  color: inherit;
  text-decoration: none;
  background: var(--app-surface-soft);
}

.profile-grid__item img {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: cover;
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

.profile-grid__skeleton {
  aspect-ratio: 1 / 1;
  background: var(--app-surface-soft);
  animation: profile-pulse 1.4s ease-in-out infinite;
}

@keyframes profile-pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.45; }
}

@media (min-width: 768px) {
  .profile-header {
    grid-template-columns: minmax(10rem, 0.8fr) minmax(0, 1.2fr);
    align-items: start;
  }

  .profile-summary {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 767.98px) {
  .profile-tabs {
    gap: 0.85rem;
    overflow-x: auto;
    justify-content: flex-start;
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
