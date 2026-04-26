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
const currentPage = ref(1)
const hasMore = ref(false)
const isLoading = ref(false)
const loadError = ref('')

let debounceTimer = null

watch(searchQuery, (query) => {
  clearTimeout(debounceTimer)
  loadError.value = ''
  if (!query.trim()) {
    loadPeople({ reset: true })
    return
  }
  debounceTimer = setTimeout(() => searchPeopleInner(query.trim()), 350)
})

function clearSearch() {
  searchQuery.value = ''
}

const viewerFollowingSet = ref(new Set())
const pendingFollowIds = ref(new Set())
const pendingTargets = ref(new Set())

function isFollowing(account) { return viewerFollowingSet.value.has(account.id) }
function isFollowPending(account) { return pendingFollowIds.value.has(account.id) }

function getProfileLink(username) {
  return currentUser.value?.username === username
    ? { name: 'perfil' }
    : { name: 'perfil', query: { user: username } }
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
  try {
    const response = await usersService.search(query, 30)
    const users = (response.data ?? []).map(normalizeUser).filter(Boolean)
    people.value = users
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
  try {
    const page = reset ? 1 : currentPage.value + 1
    const response = await usersService.suggestions(20, page)
    const users = (response.data ?? []).map(normalizeUser).filter(Boolean)
    people.value = reset ? users : [...people.value, ...users]
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
  if (!currentUser.value?.id || pendingTargets.value.has(account.id)) return
  pendingTargets.value = new Set([...pendingTargets.value, account.id])
  try {
    if (isFollowing(account)) {
      await followsService.unfollow(account.id)
      viewerFollowingSet.value = new Set([...viewerFollowingSet.value].filter((id) => id !== account.id))
    } else if (isFollowPending(account)) {
      await followsService.unfollow(account.id)
      pendingFollowIds.value = new Set([...pendingFollowIds.value].filter((id) => id !== account.id))
    } else {
      const result = await followsService.follow(account.id)
      if (result.status === 'pending') {
        pendingFollowIds.value = new Set([...pendingFollowIds.value, account.id])
      } else {
        viewerFollowingSet.value = new Set([...viewerFollowingSet.value, account.id])
      }
    }
  } catch {
    // silent
  } finally {
    pendingTargets.value = new Set([...pendingTargets.value].filter((id) => id !== account.id))
  }
}

function formatCount(n) {
  if (n >= 1_000_000) return (n / 1_000_000).toFixed(1).replace('.0', '') + 'M'
  if (n >= 1000) return (n / 1000).toFixed(1).replace('.0', '') + 'mil'
  return String(n)
}

onMounted(() => loadPeople({ reset: true }))
</script>

<template>
  <div class="ps">
    <h1 class="ps__title">Pesquisar</h1>

    <!-- Search field -->
    <div class="ps__field">
      <span class="ps__field-icon">
        <AppIcon name="search" />
      </span>
      <input
        v-model="searchQuery"
        class="ps__input"
        type="search"
        placeholder="Pesquisar"
        autocomplete="off"
        spellcheck="false"
      />
      <button
        v-if="searchQuery"
        class="ps__clear"
        type="button"
        aria-label="Limpar"
        @click="clearSearch"
      >
        <AppIcon name="close" />
      </button>
    </div>

    <!-- Error -->
    <p v-if="loadError" class="ps__error" role="alert">{{ loadError }}</p>

    <!-- Skeleton -->
    <div v-if="isLoading && people.length === 0" class="ps__skeleton-list">
      <div v-for="n in 6" :key="n" class="ps__skeleton-row" />
    </div>

    <template v-else>
      <!-- Section header -->
      <div class="ps__section-head">
        <span class="ps__section-title">
          {{ searchQuery ? `${people.length} resultado${people.length !== 1 ? 's' : ''}` : 'Sugestões' }}
        </span>
      </div>

      <!-- Empty state -->
      <div v-if="people.length === 0 && !isLoading" class="ps__empty">
        <p>{{ searchQuery ? `Nenhum usuário encontrado para "${searchQuery}".` : 'Nenhuma sugestão no momento.' }}</p>
      </div>

      <!-- People list -->
      <ul v-else class="ps__list">
        <li v-for="account in people" :key="account.id" class="ps__row">
          <RouterLink :to="getProfileLink(account.username)" class="ps__avatar">
            <ProfileAvatar
              :name="account.name"
              :username="account.username"
              :avatar-url="account.avatarUrl"
              :colors="account.colors"
              size="sm"
            />
          </RouterLink>

          <RouterLink :to="getProfileLink(account.username)" class="ps__row-text">
            <span class="ps__username">{{ account.username }}</span>
            <span class="ps__meta">
              {{ account.name }}
              <template v-if="account.followersCount != null">
                · {{ formatCount(account.followersCount) }} seguidores
              </template>
            </span>
          </RouterLink>

          <button
            v-if="account.id !== currentUser?.id"
            class="ps__follow-btn"
            :class="{
              'ps__follow-btn--following': isFollowing(account) || isFollowPending(account)
            }"
            type="button"
            :disabled="pendingTargets.has(account.id)"
            @click="handleToggleFollow(account)"
          >
            {{ isFollowing(account) ? 'Seguindo' : isFollowPending(account) ? 'Solicitado' : 'Seguir' }}
          </button>
        </li>
      </ul>

      <!-- Load more -->
      <div v-if="hasMore" class="ps__more">
        <button
          class="ps__more-btn"
          type="button"
          :disabled="isLoading"
          @click="loadPeople({ reset: false })"
        >
          {{ isLoading ? 'Carregando...' : 'Carregar mais' }}
        </button>
      </div>
    </template>
  </div>
</template>

<style scoped>
.ps {
  max-width: 600px;
  margin: 0 auto;
  padding-bottom: 3rem;
}

/* ── Title ── */
.ps__title {
  margin: 0.75rem 0 1.1rem;
  font-size: 1.5rem;
  font-weight: 300;
  color: var(--app-text);
}

/* ── Search field ── */
.ps__field {
  position: relative;
  display: flex;
  align-items: center;
  margin-bottom: 1.5rem;
  border: 1px solid var(--app-border);
  border-radius: 8px;
  background: var(--app-surface-soft);
  transition: border-color 180ms ease;
}

.ps__field:focus-within {
  border-color: var(--app-muted);
}

.ps__field-icon {
  position: absolute;
  left: 14px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--app-muted);
  display: flex;
  pointer-events: none;
}

.ps__field-icon .app-icon {
  width: 16px;
  height: 16px;
}

.ps__input {
  width: 100%;
  padding: 12px 36px 12px 42px;
  border: 0;
  background: transparent;
  color: var(--app-text);
  font-size: 0.9rem;
  font-family: inherit;
  outline: none;
}

.ps__input::placeholder {
  color: var(--app-muted);
}

.ps__input::-webkit-search-cancel-button {
  display: none;
}

.ps__clear {
  position: absolute;
  right: 10px;
  top: 50%;
  transform: translateY(-50%);
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: var(--app-muted);
  color: var(--app-bg);
  display: grid;
  place-items: center;
  border: 0;
  cursor: pointer;
  flex-shrink: 0;
}

.ps__clear .app-icon {
  width: 10px;
  height: 10px;
}

/* ── Error ── */
.ps__error {
  margin: 0 0 1rem;
  padding: 0.85rem 1rem;
  border: 1px solid rgba(255, 48, 64, 0.28);
  border-radius: 0.75rem;
  color: #ffb4ba;
  background: rgba(255, 48, 64, 0.08);
  font-size: 0.88rem;
}

/* ── Section head ── */
.ps__section-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 0.5rem;
}

.ps__section-title {
  font-size: 0.95rem;
  font-weight: 600;
  color: var(--app-text);
}

/* ── List ── */
.ps__list {
  list-style: none;
  margin: 0;
  padding: 0;
}

.ps__row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 8px 6px;
  border-radius: 8px;
  transition: background 150ms ease;
}

.ps__row:hover {
  background: var(--app-surface-soft);
}

.ps__avatar {
  flex-shrink: 0;
  line-height: 0;
  border-radius: 50%;
}

.ps__row-text {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
  text-decoration: none;
  color: inherit;
}

.ps__username {
  font-size: 0.9rem;
  font-weight: 600;
  color: var(--app-text);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.ps__meta {
  font-size: 0.82rem;
  color: var(--app-muted);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* ── Follow button ── */
.ps__follow-btn {
  flex-shrink: 0;
  padding: 0.38rem 0.9rem;
  border-radius: 6px;
  font-size: 0.82rem;
  font-weight: 700;
  border: 0;
  cursor: pointer;
  transition: opacity 150ms ease, background 150ms ease;
  background: var(--app-accent);
  color: #fff;
}

.ps__follow-btn--following {
  background: var(--app-surface-soft);
  color: var(--app-text);
  border: 1px solid var(--app-border);
}

.ps__follow-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.ps__follow-btn:not(:disabled):hover {
  opacity: 0.85;
}

/* ── Empty ── */
.ps__empty {
  padding: 2.5rem 1rem;
  text-align: center;
  color: var(--app-muted);
  font-size: 0.9rem;
}

.ps__empty p { margin: 0; }

/* ── Skeleton ── */
.ps__skeleton-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.ps__skeleton-row {
  height: 60px;
  border-radius: 8px;
  background: var(--app-surface-soft);
  animation: pulse 1.4s ease-in-out infinite;
}

.ps__skeleton-row:nth-child(2) { animation-delay: 0.1s; }
.ps__skeleton-row:nth-child(3) { animation-delay: 0.2s; }
.ps__skeleton-row:nth-child(4) { animation-delay: 0.15s; }

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.4; }
}

/* ── Load more ── */
.ps__more {
  display: flex;
  justify-content: center;
  padding: 1.25rem 0 0.5rem;
}

.ps__more-btn {
  min-width: 14rem;
  padding: 0.8rem 1.1rem;
  border: 1px solid var(--app-border);
  border-radius: 0.8rem;
  color: var(--app-text);
  font-weight: 700;
  background: var(--app-surface-soft);
  transition: background 180ms ease, border-color 180ms ease;
}

.ps__more-btn:hover:not(:disabled) {
  border-color: var(--app-border-strong);
  background: #1b1b1b;
}

.ps__more-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>
