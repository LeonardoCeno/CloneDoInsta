<script setup>
import { onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import ProfileAvatar from '@/components/profile/ProfileAvatar.vue'
import AppIcon from '@/components/layout/AppIcon.vue'
import { useAuth } from '@/composables/useAuth'
import * as usersService from '@/services/users.service'
import * as followsService from '@/services/follows.service'
import { extractErrorMessage } from '@/services/api'
import { normalizeUser } from '@/stores/profileUtils'

const { currentUser } = useAuth()

const searchQuery = ref('')
const isSearching = ref(false)

const people = ref([])
const totalPeople = ref(0)
const currentPage = ref(1)
const hasMore = ref(false)
const isLoading = ref(false)
const loadError = ref('')
const feedbackMessage = ref('')

let debounceTimer = null

watch(searchQuery, (query) => {
  clearTimeout(debounceTimer)
  loadError.value = ''

  if (!query.trim()) {
    loadPeople({ reset: true })
    return
  }

  debounceTimer = setTimeout(() => {
    searchPeopleInner(query.trim())
  }, 350)
})

function clearSearch() {
  searchQuery.value = ''
}

const viewerFollowingSet = ref(new Set())
const pendingFollowIds = ref(new Set())
const pendingTargets = ref(new Set())

function isFollowing(account) {
  return viewerFollowingSet.value.has(account.id)
}

function isFollowPending(account) {
  return pendingFollowIds.value.has(account.id)
}

function getProfileLink(username) {
  if (currentUser.value?.username === username) {
    return { name: 'perfil' }
  }
  return { name: 'perfil', query: { user: username } }
}

function seedSetsFromPeople(users) {
  const following = new Set(viewerFollowingSet.value)
  const pending = new Set(pendingFollowIds.value)
  for (const u of users) {
    if (u.isFollowing) following.add(u.id)
    if (u.isFollowPending) pending.add(u.id)
  }
  viewerFollowingSet.value = following
  pendingFollowIds.value = pending
}

async function searchPeopleInner(query) {
  isSearching.value = true
  isLoading.value = true
  loadError.value = ''

  try {
    const response = await usersService.search(query, 30)
    const users = (response.data ?? []).map(normalizeUser).filter(Boolean)
    people.value = users
    totalPeople.value = Number(response.total ?? users.length)
    hasMore.value = false
    seedSetsFromPeople(users)
  } catch (error) {
    loadError.value = extractErrorMessage(error, 'Não foi possível pesquisar agora.')
  } finally {
    isLoading.value = false
    isSearching.value = false
  }
}

async function loadPeople({ reset = true } = {}) {
  isLoading.value = true
  loadError.value = ''

  try {
    const page = reset ? 1 : currentPage.value + 1
    const response = await usersService.suggestions(20, page)
    const users = (response.data ?? []).map(normalizeUser).filter(Boolean)

    people.value = reset ? users : [...people.value, ...users]
    totalPeople.value = Number(response.total ?? people.value.length)
    currentPage.value = Number(response.current_page ?? page)
    hasMore.value = Boolean(response.next_page_url)
    seedSetsFromPeople(users)
  } catch (error) {
    loadError.value = extractErrorMessage(error, 'Não foi possível carregar sugestões agora.')
  } finally {
    isLoading.value = false
  }
}

async function handleToggleFollow(account) {
  if (!currentUser.value?.id || pendingTargets.value.has(account.id)) {
    return
  }

  pendingTargets.value = new Set([...pendingTargets.value, account.id])

  try {
    if (isFollowing(account)) {
      await followsService.unfollow(account.id)
      viewerFollowingSet.value = new Set([...viewerFollowingSet.value].filter((id) => id !== account.id))
      feedbackMessage.value = `Você deixou de seguir @${account.username}.`
    } else if (isFollowPending(account)) {
      await followsService.unfollow(account.id)
      pendingFollowIds.value = new Set([...pendingFollowIds.value].filter((id) => id !== account.id))
      feedbackMessage.value = `Solicitação para @${account.username} cancelada.`
    } else {
      const result = await followsService.follow(account.id)
      if (result.status === 'pending') {
        pendingFollowIds.value = new Set([...pendingFollowIds.value, account.id])
        feedbackMessage.value = `Solicitação enviada para @${account.username}.`
      } else {
        viewerFollowingSet.value = new Set([...viewerFollowingSet.value, account.id])
        feedbackMessage.value = `Agora você segue @${account.username}.`
      }
    }
  } catch (error) {
    feedbackMessage.value = extractErrorMessage(
      error,
      'Não foi possível atualizar esse perfil agora.',
    )
  } finally {
    pendingTargets.value = new Set([...pendingTargets.value].filter((id) => id !== account.id))
  }
}

onMounted(() => {
  loadPeople({ reset: true })
})
</script>

<template>
  <section class="discover">
    <section class="discover__hero card border-0">
      <div>
        <span class="discover__eyebrow">Pessoas que você pode conhecer</span>
        <h2>Descubra novos perfis</h2>
        <p>
          Conheça todas as contas da rede e comece a seguir quem combina com o seu radar.
        </p>
      </div>

      <div class="discover__hero-stat">
        <strong>{{ totalPeople }}</strong>
        <span>perfis disponíveis</span>
      </div>
    </section>

    <div class="discover__search">
      <label class="discover__search-label" for="discover-search">
        <AppIcon name="search" />
      </label>
      <input
        id="discover-search"
        v-model="searchQuery"
        class="discover__search-input"
        type="search"
        placeholder="Pesquisar por nome ou @usuário..."
        autocomplete="off"
        spellcheck="false"
      />
      <button
        v-if="searchQuery"
        class="discover__search-clear"
        type="button"
        aria-label="Limpar pesquisa"
        @click="clearSearch"
      >
        <AppIcon name="close" />
      </button>
    </div>

    <p v-if="loadError" class="discover__feedback is-error" role="alert">
      {{ loadError }}
    </p>

    <p v-if="feedbackMessage" class="discover__feedback" role="status">
      {{ feedbackMessage }}
    </p>

    <p v-if="searchQuery && !isLoading && people.length > 0" class="discover__search-meta">
      {{ people.length }} resultado{{ people.length !== 1 ? 's' : '' }} para
      <strong>{{ searchQuery }}</strong>
    </p>

    <section v-if="people.length > 0" class="discover__grid">
      <article
        v-for="account in people"
        :key="account.id"
        class="discover__card card border-0"
      >
        <RouterLink :to="getProfileLink(account.username)" class="discover__identity">
          <ProfileAvatar
            :name="account.name"
            :username="account.username"
            :avatar-url="account.avatarUrl"
            :colors="account.colors"
            size="md"
          />

          <div class="discover__copy">
            <strong>{{ account.name }}</strong>
            <span>@{{ account.username }}</span>
            <p v-if="account.bio">{{ account.bio }}</p>
          </div>
        </RouterLink>

        <button
          class="btn"
          :class="(isFollowing(account) || isFollowPending(account)) ? 'btn-outline-secondary' : 'btn-primary'"
          type="button"
          :disabled="pendingTargets.has(account.id)"
          @click="handleToggleFollow(account)"
        >
          {{ isFollowing(account) ? 'Seguindo' : isFollowPending(account) ? 'Solicitado' : 'Seguir' }}
        </button>
      </article>
    </section>

    <section v-else-if="!isLoading" class="discover__empty card border-0">
      <h3>{{ searchQuery ? 'Nenhum resultado' : 'Nenhum perfil para sugerir' }}</h3>
      <p>
        {{
          searchQuery
            ? `Nenhum usuário encontrado para "${searchQuery}". Tente outro nome ou @usuário.`
            : 'Assim que novas pessoas entrarem na rede, elas aparecem por aqui.'
        }}
      </p>
    </section>

    <section v-else class="discover__empty card border-0">
      <p class="mb-0">Carregando pessoas...</p>
    </section>

    <div v-if="hasMore" class="discover__more">
      <button
        class="btn btn-outline-secondary"
        type="button"
        :disabled="isLoading"
        @click="loadPeople({ reset: false })"
      >
        {{ isLoading ? 'Carregando...' : 'Carregar mais pessoas' }}
      </button>
    </div>
  </section>
</template>

<style scoped>
.discover {
  display: grid;
  gap: 1rem;
}

.discover__hero,
.discover__card,
.discover__empty {
  padding: 1.4rem;
  border-radius: 1.75rem;
  background: var(--app-surface);
}

.discover__hero {
  display: grid;
  gap: 1rem;
}

.discover__eyebrow {
  display: inline-block;
  margin-bottom: 0.35rem;
  color: var(--app-accent-strong);
  font-size: 0.78rem;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.discover__hero h2,
.discover__empty h3 {
  margin: 0 0 0.35rem;
  font-size: clamp(1.6rem, 4vw, 2.3rem);
  font-weight: 800;
}

.discover__hero p,
.discover__copy span,
.discover__copy p,
.discover__empty p {
  margin: 0;
  color: var(--app-muted);
  line-height: 1.65;
}

.discover__hero-stat {
  display: grid;
  gap: 0.2rem;
  padding: 1rem;
  border: 1px solid var(--app-border);
  border-radius: 1.15rem;
  background: var(--app-surface-soft);
}

.discover__hero-stat strong {
  font-size: 1.4rem;
}

.discover__feedback {
  margin: 0;
  padding: 0.95rem 1rem;
  border: 1px solid var(--app-border);
  border-radius: 1rem;
  color: var(--app-text);
  font-weight: 600;
  background: var(--app-surface-soft);
}

.discover__feedback.is-error {
  color: #ffb4ba;
  border-color: rgba(255, 48, 64, 0.28);
  background: rgba(255, 48, 64, 0.08);
}

.discover__grid {
  display: grid;
  gap: 1rem;
}

.discover__card {
  display: grid;
  gap: 1rem;
}

.discover__identity {
  display: flex;
  align-items: flex-start;
  gap: 0.9rem;
  color: inherit;
  text-decoration: none;
}

.discover__copy {
  display: grid;
  gap: 0.2rem;
}

.discover__copy strong {
  font-size: 1.05rem;
}

.discover__search {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  padding: 0.75rem 1rem;
  border: 1px solid var(--app-border);
  border-radius: 1.15rem;
  background: var(--app-surface);
  transition: border-color 180ms ease, box-shadow 180ms ease;
}

.discover__search:focus-within {
  border-color: rgba(0, 149, 246, 0.5);
  box-shadow: 0 0 0 3px rgba(0, 149, 246, 0.12);
}

.discover__search-label {
  display: grid;
  place-items: center;
  color: var(--app-muted);
  flex-shrink: 0;
}

.discover__search-label .app-icon {
  width: 18px;
  height: 18px;
}

.discover__search-input {
  flex: 1;
  min-width: 0;
  padding: 0;
  border: 0;
  color: var(--app-text);
  font-size: 0.97rem;
  background: transparent;
}

.discover__search-input::placeholder {
  color: var(--app-muted);
}

.discover__search-input:focus {
  outline: none;
}

.discover__search-input::-webkit-search-cancel-button {
  display: none;
}

.discover__search-clear {
  display: grid;
  place-items: center;
  width: 1.6rem;
  height: 1.6rem;
  padding: 0;
  border: 0;
  border-radius: 50%;
  color: var(--app-muted);
  background: var(--app-surface-soft);
  cursor: pointer;
  flex-shrink: 0;
  transition: color 150ms ease, background 150ms ease;
}

.discover__search-clear:hover {
  color: var(--app-text);
  background: var(--app-border);
}

.discover__search-clear .app-icon {
  width: 13px;
  height: 13px;
}

.discover__search-meta {
  margin: 0;
  padding: 0 0.25rem;
  color: var(--app-muted);
  font-size: 0.88rem;
}

.discover__search-meta strong {
  color: var(--app-text);
}

.discover__more {
  display: flex;
  justify-content: center;
  padding: 0.5rem 0;
}

@media (min-width: 768px) {
  .discover__hero,
  .discover__card {
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: center;
  }
}
</style>
