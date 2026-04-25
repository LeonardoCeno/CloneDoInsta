<script setup>
import { computed, ref, watch, onMounted, onUnmounted } from 'vue'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'
import AppIcon from '@/components/layout/AppIcon.vue'
import ProfileAvatar from '@/components/profile/ProfileAvatar.vue'
import ToastContainer from '@/components/shared/ToastContainer.vue'
import { useAuth } from '@/composables/useAuth'
import { useAuthStore } from '@/stores/auth'
import * as followsService from '@/services/follows.service'
import * as usersService from '@/services/users.service'
import { deleteAccount } from '@/services/users.service'
import { normalizeUser } from '@/stores/profileUtils'
import { useNotificationsStore } from '@/stores/notifications'
import { useFeedStore } from '@/stores/feed'
import { useToastStore } from '@/stores/toast'

const notificationsStore = useNotificationsStore()
const authStore = useAuthStore()
const feedStore = useFeedStore()
const toastStore = useToastStore()

const showMoreMenu = ref(false)
const privacyPending = ref(false)
const showDeleteConfirm = ref(false)
const deletePending = ref(false)

let pollInterval = null

onMounted(async () => {
  await notificationsStore.fetchUnreadCount()
  pollInterval = setInterval(() => notificationsStore.fetchUnreadCount(), 60_000)
})

onUnmounted(() => clearInterval(pollInterval))

const route = useRoute()
const router = useRouter()
const { currentUser, logout } = useAuth()

const navItems = [
  { name: 'feed',          label: 'Home',          icon: 'home'     },
  { name: 'reels',         label: 'Reels',          icon: 'reels'    },
  { name: 'explorar',      label: 'Explorar',       icon: 'discover' },
  { name: 'descobrir',     label: 'Buscar',         icon: 'search'   },
  { name: 'notificacoes',  label: 'Notificações',   icon: 'heart'    },
  { name: 'criar',         label: 'Criar',          icon: 'create'   },
  { name: 'salvos',        label: 'Salvos',         icon: 'save'     },
  { name: 'perfil',        label: 'Perfil',         icon: 'profile'  },
]

const activeNavName = computed(() => route.meta.navItem ?? route.name)
const isFeedRoute = computed(() => activeNavName.value === 'feed')
const isReelsRoute = computed(() => activeNavName.value === 'reels')
const contentMode = computed(() => {
  if (isFeedRoute.value) return 'feed'
  if (activeNavName.value === 'perfil') return 'profile'
  if (isReelsRoute.value) return 'reels'
  return 'default'
})
const accountHandle = computed(() =>
  currentUser.value?.username ? `@${currentUser.value.username}` : '@instaclone',
)
const accountName = computed(() => currentUser.value?.name || 'Sua conta')

const railSuggestions = ref([])
const loadingSuggestions = ref(false)
const followPendingIds = ref(new Set())

function getProfileRoute(username) {
  if (currentUser.value?.username === username) {
    return { name: 'perfil' }
  }

  return {
    name: 'perfil',
    query: { user: username },
  }
}

function updatePendingSet(accountId, shouldAdd) {
  const next = new Set(followPendingIds.value)

  if (shouldAdd) {
    next.add(accountId)
  } else {
    next.delete(accountId)
  }

  followPendingIds.value = next
}

async function loadSuggestions() {
  if (!currentUser.value?.id || !isFeedRoute.value) {
    railSuggestions.value = []
    return
  }

  loadingSuggestions.value = true

  try {
    const response = await usersService.suggestions(6, 1)
    railSuggestions.value = (response.data ?? [])
      .map(normalizeUser)
      .filter((account) => account && account.id !== currentUser.value?.id)
      .slice(0, 5)
  } catch {
    railSuggestions.value = []
  } finally {
    loadingSuggestions.value = false
  }
}

async function handleFollowSuggestion(account) {
  if (!account || followPendingIds.value.has(account.id)) {
    return
  }

  updatePendingSet(account.id, true)

  try {
    await followsService.follow(account.id)
    railSuggestions.value = railSuggestions.value.filter((item) => item.id !== account.id)
    toastStore.show(`Agora você segue @${account.username}.`, 'success')
    feedStore.fetchFeed({ reset: true })
  } finally {
    updatePendingSet(account.id, false)
  }
}

async function handleLogout() {
  showMoreMenu.value = false
  showDeleteConfirm.value = false
  await logout()
  router.replace({ name: 'login' })
}

async function handleDeleteAccount() {
  if (deletePending.value) return
  deletePending.value = true
  try {
    await deleteAccount()
    await logout()
    router.replace({ name: 'login' })
  } finally {
    deletePending.value = false
  }
}

function closeMoreMenu() {
  showMoreMenu.value = false
  showDeleteConfirm.value = false
}

async function handleTogglePrivacy() {
  if (privacyPending.value) return
  privacyPending.value = true
  try {
    await authStore.togglePrivacy()
  } finally {
    privacyPending.value = false
  }
}

watch([() => currentUser.value?.id, isFeedRoute], loadSuggestions, { immediate: true })
</script>

<template>
  <RouterView v-slot="{ Component }">
    <div class="ig-layout" :class="`is-${contentMode}`">
      <aside class="ig-sidebar">
        <RouterLink class="ig-brand" :to="{ name: 'feed' }" aria-label="Ir para o feed">
          <span class="ig-brand__glyph">
            <AppIcon name="instagram" />
          </span>
          <span class="ig-brand__wordmark">InstaClone</span>
        </RouterLink>

        <nav class="ig-nav" aria-label="Navegação principal">
          <RouterLink
            v-for="item in navItems"
            :key="item.name"
            :to="{ name: item.name }"
            class="ig-nav__link"
            :class="{ 'is-active': activeNavName === item.name }"
            :title="item.label"
          >
            <span class="ig-nav__icon-wrap">
              <AppIcon :name="item.icon" />
              <span
                v-if="item.name === 'notificacoes' && notificationsStore.unreadCount > 0"
                class="ig-nav__badge"
              >
                {{ notificationsStore.unreadCount > 99 ? '99+' : notificationsStore.unreadCount }}
              </span>
            </span>
            <span class="ig-nav__label">{{ item.label }}</span>
          </RouterLink>
        </nav>

        <div class="ig-sidebar__footer">
          <RouterLink
            :to="{ name: 'perfil' }"
            class="ig-sidebar__account"
            title="Abrir seu perfil"
          >
            <ProfileAvatar
              :name="currentUser?.name"
              :username="currentUser?.username"
              :avatar-url="currentUser?.avatarUrl"
              :colors="currentUser?.colors"
              size="sm"
            />
            <span class="ig-nav__label">Perfil</span>
          </RouterLink>

          <button class="ig-sidebar__more" type="button" title="Mais opções" @click="showMoreMenu = true">
            <AppIcon name="menu" />
            <span class="ig-nav__label">Mais</span>
          </button>
        </div>
      </aside>

      <div class="ig-content">
        <header v-if="isFeedRoute" class="ig-topbar">
          <RouterLink class="ig-search" :to="{ name: 'descobrir' }">
            <AppIcon name="search" />
            <span>Pesquisar perfis</span>
          </RouterLink>
        </header>

        <main class="ig-main" :class="`ig-main--${contentMode}`">
          <component :is="Component" />
        </main>
      </div>

      <!-- "Mais" popup menu -->
      <Teleport to="body">
        <Transition name="mais-fade">
          <div v-if="showMoreMenu" class="mais-backdrop" @click.self="closeMoreMenu">
            <div class="mais-panel" role="dialog" aria-label="Mais opções">
              <template v-if="!showDeleteConfirm">
                <button
                  class="mais-item"
                  type="button"
                  :disabled="privacyPending"
                  @click="handleTogglePrivacy"
                >
                  <AppIcon :name="currentUser?.isPrivate ? 'unlock' : 'lock'" />
                  <span>{{ currentUser?.isPrivate ? 'Tornar perfil público' : 'Tornar perfil privado' }}</span>
                </button>

                <hr class="mais-divider" />

                <button class="mais-item mais-item--danger" type="button" @click="handleLogout">
                  <AppIcon name="logout" />
                  <span>Encerrar sessão</span>
                </button>

                <hr class="mais-divider" />

                <button class="mais-item mais-item--danger" type="button" @click="showDeleteConfirm = true">
                  <AppIcon name="trash" />
                  <span>Excluir conta</span>
                </button>
              </template>

              <template v-else>
                <p class="mais-confirm-text">
                  Tem certeza? Todos os seus posts, seguidores e dados serão excluídos permanentemente. Essa ação não pode ser desfeita.
                </p>
                <button
                  class="mais-item mais-item--danger"
                  type="button"
                  :disabled="deletePending"
                  @click="handleDeleteAccount"
                >
                  <AppIcon name="trash" />
                  <span>{{ deletePending ? 'Excluindo...' : 'Sim, excluir minha conta' }}</span>
                </button>
                <button class="mais-item" type="button" @click="showDeleteConfirm = false">
                  <span>Cancelar</span>
                </button>
              </template>
            </div>
          </div>
        </Transition>
      </Teleport>

      <aside v-if="isFeedRoute" class="ig-rail">
        <section class="ig-rail__account">
          <div class="ig-rail__identity">
            <ProfileAvatar
              :name="currentUser?.name"
              :username="currentUser?.username"
              :avatar-url="currentUser?.avatarUrl"
              :colors="currentUser?.colors"
              size="md"
            />

            <div class="ig-rail__copy">
              <strong>{{ accountHandle }}</strong>
              <span>{{ accountName }}</span>
            </div>
          </div>

          <RouterLink class="ig-rail__action" :to="{ name: 'perfil-editar' }">
            Editar
          </RouterLink>
        </section>

        <section class="ig-rail__suggestions">
          <div class="ig-rail__heading">
            <strong>Sugestões para você</strong>
            <RouterLink :to="{ name: 'descobrir' }">Ver tudo</RouterLink>
          </div>

          <ul class="ig-rail__list">
            <li v-if="loadingSuggestions" class="ig-rail__empty">Carregando sugestões...</li>
            <li v-else-if="railSuggestions.length === 0" class="ig-rail__empty">
              Sem novas contas para sugerir agora.
            </li>
            <li v-for="account in railSuggestions" :key="account.id" class="ig-rail__list-item">
              <RouterLink :to="getProfileRoute(account.username)" class="ig-rail__item">
                <ProfileAvatar
                  :name="account.name"
                  :username="account.username"
                  :avatar-url="account.avatarUrl"
                  :colors="account.colors"
                  size="sm"
                />

                <span>
                  <strong>{{ account.username }}</strong>
                  <small>{{ account.name }}</small>
                </span>
              </RouterLink>

              <button
                class="ig-rail__follow"
                type="button"
                :disabled="followPendingIds.has(account.id)"
                @click="handleFollowSuggestion(account)"
              >
                {{ followPendingIds.has(account.id) ? '...' : 'Seguir' }}
              </button>
            </li>
          </ul>
        </section>

        <p class="ig-rail__meta">
          Sobre · Ajuda · API · Privacidade · Termos · Localizações
        </p>
      </aside>
    </div>
  </RouterView>

  <ToastContainer />
</template>

<style scoped>
.mais-backdrop {
  position: fixed;
  inset: 0;
  z-index: 200;
  display: flex;
  align-items: flex-end;
  background: rgba(0, 0, 0, 0.55);
}

.mais-panel {
  width: 100%;
  max-width: 400px;
  margin: 0 auto 1.5rem;
  padding: 0.5rem 0;
  border-radius: 1.25rem;
  background: #1c1c1c;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.6);
}

.mais-item {
  display: flex;
  align-items: center;
  gap: 0.85rem;
  width: 100%;
  padding: 1rem 1.25rem;
  border: 0;
  color: #f5f5f5;
  font-size: 0.97rem;
  font-weight: 500;
  text-align: left;
  background: none;
  cursor: pointer;
  transition: background 150ms ease;
}

.mais-item:hover {
  background: rgba(255, 255, 255, 0.07);
}

.mais-item--danger {
  color: #ff5c5c;
}

.mais-divider {
  margin: 0.25rem 0;
  border-color: rgba(255, 255, 255, 0.1);
}

.mais-confirm-text {
  margin: 0;
  padding: 0.75rem 1rem;
  font-size: 0.85rem;
  color: var(--app-muted);
  line-height: 1.5;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.mais-fade-enter-active,
.mais-fade-leave-active {
  transition: opacity 180ms ease;
}

.mais-fade-enter-from,
.mais-fade-leave-to {
  opacity: 0;
}

.mais-fade-enter-active .mais-panel,
.mais-fade-leave-active .mais-panel {
  transition: transform 180ms ease;
}

.mais-fade-enter-from .mais-panel,
.mais-fade-leave-to .mais-panel {
  transform: translateY(1.5rem);
}
</style>
