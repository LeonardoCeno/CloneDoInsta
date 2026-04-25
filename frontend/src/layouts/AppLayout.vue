<script setup>
import { computed, ref, watch, onMounted, onUnmounted } from 'vue'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'
import AppIcon from '@/components/layout/AppIcon.vue'
import ProfileAvatar from '@/components/profile/ProfileAvatar.vue'
import { useAuth } from '@/composables/useAuth'
import * as followsService from '@/services/follows.service'
import * as usersService from '@/services/users.service'
import { normalizeUser } from '@/stores/profileUtils'
import { useNotificationsStore } from '@/stores/notifications'

const notificationsStore = useNotificationsStore()

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
  { name: 'explorar',      label: 'Explorar',       icon: 'discover' },
  { name: 'descobrir',     label: 'Buscar',         icon: 'search'   },
  { name: 'notificacoes',  label: 'Notificações',   icon: 'heart'    },
  { name: 'criar',         label: 'Criar',          icon: 'create'   },
  { name: 'salvos',        label: 'Salvos',         icon: 'save'     },
  { name: 'perfil',        label: 'Perfil',         icon: 'profile'  },
]

const activeNavName = computed(() => route.meta.navItem ?? route.name)
const isFeedRoute = computed(() => activeNavName.value === 'feed')
const contentMode = computed(() => {
  if (isFeedRoute.value) {
    return 'feed'
  }
  if (activeNavName.value === 'perfil') {
    return 'profile'
  }
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
  } finally {
    updatePendingSet(account.id, false)
  }
}

async function handleLogout() {
  await logout()
  router.replace({ name: 'login' })
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

          <button class="ig-sidebar__more" type="button" title="Encerrar sessão" @click="handleLogout">
            <AppIcon name="menu" />
            <span class="ig-nav__label">Sair</span>
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
</template>
