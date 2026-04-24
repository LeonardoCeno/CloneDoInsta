<script setup>
import { computed, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import ProfileAvatar from '@/components/profile/ProfileAvatar.vue'
import { useAuth } from '@/composables/useAuth'
import * as usersService from '@/services/users.service'
import * as followsService from '@/services/follows.service'
import { extractErrorMessage } from '@/services/api'
import { normalizeUser } from '@/stores/profileUtils'

const route = useRoute()
const { currentUser } = useAuth()

const feedbackMessage = ref('')
const loadError = ref('')
const isLoading = ref(false)

const profile = ref(null)
const people = ref([])
const totalPeople = ref(0)
const currentPage = ref(1)
const hasMore = ref(false)
const viewerFollowingSet = ref(new Set())
const pendingTargets = ref(new Set())

const selectedUsername = computed(() =>
  typeof route.query.user === 'string' ? route.query.user.trim().toLowerCase() : '',
)

const listType = computed(() => {
  return route.params.type === 'seguidores' || route.params.type === 'seguindo'
    ? route.params.type
    : ''
})

const isOwnProfile = computed(
  () => Boolean(profile.value && currentUser.value && profile.value.id === currentUser.value.id),
)

const listTitle = computed(() => (listType.value === 'seguidores' ? 'Seguidores' : 'Seguindo'))
const listDescription = computed(() => {
  if (!profile.value) {
    return ''
  }
  return listType.value === 'seguidores'
    ? `Pessoas que acompanham @${profile.value.username}.`
    : `Perfis acompanhados por @${profile.value.username} no momento.`
})

const backRoute = computed(() => {
  if (!profile.value) {
    return { name: 'perfil' }
  }
  return isOwnProfile.value
    ? { name: 'perfil' }
    : { name: 'perfil', query: { user: profile.value.username } }
})

function isViewer(account) {
  return Boolean(currentUser.value?.id) && currentUser.value.id === account.id
}

function isFollowing(account) {
  return viewerFollowingSet.value.has(account.id)
}

function getProfileLink(username) {
  if (currentUser.value?.username === username) {
    return { name: 'perfil' }
  }
  return { name: 'perfil', query: { user: username } }
}

async function loadProfile() {
  loadError.value = ''
  feedbackMessage.value = ''
  profile.value = null
  people.value = []
  totalPeople.value = 0
  currentPage.value = 1
  hasMore.value = false

  try {
    const targetUsername = selectedUsername.value || currentUser.value?.username
    if (!targetUsername) {
      loadError.value = 'Perfil não encontrado.'
      return
    }
    const raw = await usersService.getByUsername(targetUsername)
    profile.value = normalizeUser(raw)
  } catch (error) {
    loadError.value = extractErrorMessage(error, 'Não foi possível carregar o perfil.')
  }
}

async function loadViewerFollowing() {
  if (!currentUser.value?.id) {
    viewerFollowingSet.value = new Set()
    return
  }

  try {
    const response = await followsService.following(currentUser.value.id, 50, 1)
    const ids = (response.data ?? []).map((user) => user.id)
    viewerFollowingSet.value = new Set(ids)
  } catch {
    viewerFollowingSet.value = new Set()
  }
}

async function loadPeople({ reset = true } = {}) {
  if (!profile.value || !listType.value) {
    return
  }

  isLoading.value = true

  try {
    const page = reset ? 1 : currentPage.value + 1
    const service = listType.value === 'seguidores' ? followsService.followers : followsService.following
    const response = await service(profile.value.id, 20, page)
    const users = (response.data ?? []).map(normalizeUser).filter(Boolean)

    people.value = reset ? users : [...people.value, ...users]
    totalPeople.value = Number(response.total ?? people.value.length)
    currentPage.value = Number(response.current_page ?? page)
    hasMore.value = Boolean(response.next_page_url)
  } catch (error) {
    feedbackMessage.value = extractErrorMessage(error, 'Não foi possível carregar a lista.')
  } finally {
    isLoading.value = false
  }
}

async function handleToggleFollow(account) {
  if (!currentUser.value?.id || isViewer(account) || pendingTargets.value.has(account.id)) {
    return
  }

  pendingTargets.value.add(account.id)

  try {
    if (isFollowing(account)) {
      await followsService.unfollow(account.id)
      viewerFollowingSet.value.delete(account.id)
      feedbackMessage.value = `Você deixou de seguir @${account.username}.`
    } else {
      await followsService.follow(account.id)
      viewerFollowingSet.value.add(account.id)
      feedbackMessage.value = `Agora você segue @${account.username}.`
    }
    viewerFollowingSet.value = new Set(viewerFollowingSet.value)
  } catch (error) {
    feedbackMessage.value = extractErrorMessage(
      error,
      'Não foi possível atualizar esse perfil agora.',
    )
  } finally {
    pendingTargets.value.delete(account.id)
  }
}

watch(
  [selectedUsername, () => currentUser.value?.id],
  async () => {
    await loadProfile()
    await Promise.all([loadViewerFollowing(), loadPeople({ reset: true })])
  },
  { immediate: true },
)

watch(listType, () => {
  loadPeople({ reset: true })
})
</script>

<template>
  <section v-if="profile && listType" class="profile-list">
    <section class="profile-list__hero card border-0">
      <div>
        <span class="profile-list__eyebrow">Rede</span>
        <h2>{{ listTitle }} de {{ isOwnProfile ? 'você' : `@${profile.username}` }}</h2>
        <p>{{ listDescription }}</p>
      </div>

      <RouterLink class="btn btn-outline-secondary" :to="backRoute">Voltar ao perfil</RouterLink>
    </section>

    <p v-if="feedbackMessage" class="profile-list__feedback" role="status">
      {{ feedbackMessage }}
    </p>

    <section v-if="people.length > 0" class="profile-list__grid">
      <article
        v-for="account in people"
        :key="account.id"
        class="profile-list__card card border-0"
      >
        <RouterLink :to="getProfileLink(account.username)" class="profile-list__identity">
          <ProfileAvatar
            :name="account.name"
            :username="account.username"
            :avatar-url="account.avatarUrl"
            :colors="account.colors"
            size="md"
          />

          <div class="profile-list__copy">
            <strong>{{ account.name }}</strong>
            <span>@{{ account.username }}</span>
            <p v-if="account.bio">{{ account.bio }}</p>
          </div>
        </RouterLink>

        <button
          v-if="!isViewer(account)"
          class="btn"
          :class="isFollowing(account) ? 'btn-outline-secondary' : 'btn-primary'"
          type="button"
          :disabled="pendingTargets.has(account.id)"
          @click="handleToggleFollow(account)"
        >
          {{ isFollowing(account) ? 'Seguindo' : 'Seguir' }}
        </button>
        <span v-else class="profile-list__owner-badge">Você</span>
      </article>

      <div v-if="hasMore" class="profile-list__more">
        <button
          class="btn btn-outline-secondary"
          type="button"
          :disabled="isLoading"
          @click="loadPeople({ reset: false })"
        >
          {{ isLoading ? 'Carregando...' : 'Carregar mais' }}
        </button>
      </div>
    </section>

    <section v-else-if="!isLoading" class="profile-list__empty card border-0">
      <h3>Nenhum perfil nesta lista</h3>
      <p>
        {{ listType === 'seguidores'
          ? 'Assim que alguém acompanhar esse perfil, a lista aparece aqui.'
          : 'Quando esse perfil seguir alguém, a relação passa a aparecer aqui.' }}
      </p>
    </section>

    <section v-else class="profile-list__empty card border-0">
      <p class="mb-0">Carregando lista...</p>
    </section>
  </section>

  <section v-else-if="loadError" class="card border-0 shadow-sm">
    <div class="card-body p-4">
      <h2 class="h4 mb-3">Lista indisponível</h2>
      <p class="text-body-secondary mb-3">{{ loadError }}</p>
      <RouterLink class="btn btn-outline-secondary" :to="{ name: 'perfil' }">
        Voltar ao perfil
      </RouterLink>
    </div>
  </section>

  <section v-else class="card border-0 shadow-sm">
    <div class="card-body p-4">
      <p class="text-body-secondary mb-0">Carregando...</p>
    </div>
  </section>
</template>

<style scoped>
.profile-list {
  display: grid;
  gap: 1rem;
}

.profile-list__hero,
.profile-list__card,
.profile-list__empty {
  padding: 1.4rem;
  border-radius: 1.75rem;
  background: var(--app-surface);
}

.profile-list__hero {
  display: grid;
  gap: 1rem;
}

.profile-list__eyebrow {
  display: inline-block;
  margin-bottom: 0.35rem;
  color: var(--app-accent-strong);
  font-size: 0.78rem;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.profile-list__hero h2,
.profile-list__empty h3 {
  margin: 0 0 0.35rem;
  font-size: clamp(1.6rem, 4vw, 2.3rem);
  font-weight: 800;
}

.profile-list__hero p,
.profile-list__copy span,
.profile-list__copy p,
.profile-list__empty p {
  margin: 0;
  color: var(--app-muted);
  line-height: 1.65;
}

.profile-list__feedback {
  margin: 0;
  padding: 0.95rem 1rem;
  border: 1px solid var(--app-border);
  border-radius: 1rem;
  color: var(--app-text);
  font-weight: 600;
  background: var(--app-surface-soft);
}

.profile-list__grid {
  display: grid;
  gap: 1rem;
}

.profile-list__card {
  display: grid;
  gap: 1rem;
}

.profile-list__identity {
  display: flex;
  align-items: flex-start;
  gap: 0.9rem;
  color: inherit;
  text-decoration: none;
}

.profile-list__copy {
  display: grid;
  gap: 0.2rem;
}

.profile-list__copy strong {
  font-size: 1.05rem;
}

.profile-list__owner-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: fit-content;
  padding: 0.7rem 1rem;
  border-radius: 999px;
  font-weight: 800;
  background: var(--app-surface-soft);
}

.profile-list__more {
  display: flex;
  justify-content: center;
  padding: 0.5rem 0;
}

@media (min-width: 768px) {
  .profile-list__hero,
  .profile-list__card {
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: center;
  }
}
</style>
