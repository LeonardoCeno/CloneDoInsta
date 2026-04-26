<script setup>
import { onMounted, computed, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useNotificationsStore } from '@/stores/notifications'

const store = useNotificationsStore()

const activeTab = ref('all') // 'all' | 'follows' | 'likes' | 'comments'

const tabs = [
  { key: 'all',      label: 'Tudo' },
  { key: 'follows',  label: 'Seguidores' },
  { key: 'likes',    label: 'Curtidas' },
  { key: 'comments', label: 'Comentários' },
]

function matchesTab(n) {
  if (activeTab.value === 'all') return true
  if (activeTab.value === 'follows') return n.type === 'follow' || n.type === 'follow_request'
  if (activeTab.value === 'likes') return n.type === 'like'
  if (activeTab.value === 'comments') return n.type === 'comment'
  return true
}

const filtered = computed(() => store.notifications.filter(matchesTab))

function getGroup(createdAt) {
  if (!createdAt) return 'Anteriores'
  const now = Date.now()
  const diff = now - new Date(createdAt).getTime()
  const day = 86_400_000
  if (diff < day) return 'Hoje'
  if (diff < 7 * day) return 'Esta semana'
  if (diff < 30 * day) return 'Este mês'
  return 'Anteriores'
}

const GROUP_ORDER = ['Hoje', 'Esta semana', 'Este mês', 'Anteriores']

const grouped = computed(() => {
  const map = {}
  for (const n of filtered.value) {
    const g = getGroup(n.createdAt)
    if (!map[g]) map[g] = []
    map[g].push(n)
  }
  return GROUP_ORDER.filter((g) => map[g]).map((g) => ({ label: g, items: map[g] }))
})

function timeAgo(value) {
  if (!value) return ''
  const diff = Math.max(0, Date.now() - new Date(value).getTime())
  const min = 60_000, hour = 3_600_000, day = 86_400_000, week = 7 * day
  if (diff < min) return 'agora'
  if (diff < hour) return `${Math.floor(diff / min)}min`
  if (diff < day) return `${Math.floor(diff / hour)}h`
  if (diff < week) return `${Math.floor(diff / day)}d`
  return new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: 'short' }).format(new Date(value))
}

function describeNotification(n) {
  const actor = n.data.actor_username ? `@${n.data.actor_username}` : 'Alguém'
  if (n.type === 'like') return `${actor} curtiu seu post.`
  if (n.type === 'comment') return `${actor} comentou no seu post.`
  if (n.type === 'follow') return `${actor} passou a te seguir.`
  if (n.type === 'follow_request') return `${actor} quer te seguir.`
  return `${actor} interagiu com você.`
}

function postLink(n) {
  if (n.data.post_id) return { name: 'post-detalhes', params: { postId: n.data.post_id } }
  return null
}

function profileLink(n) {
  if (n.data.actor_username) return { name: 'perfil', query: { user: n.data.actor_username } }
  return null
}

function avatarInitial(username) {
  return (username ?? '?')[0].toUpperCase()
}

// follow-back toggle per actor
const followingBack = ref({})

function toggleFollow(actorUsername) {
  followingBack.value[actorUsername] = !followingBack.value[actorUsername]
}

onMounted(async () => {
  await store.fetchList({ reset: true })
  if (store.unreadCount > 0) await store.markAllRead()
})
</script>

<template>
  <section class="nv">
    <!-- Title -->
    <h1 class="nv__title">Notificações</h1>

    <!-- Tabs -->
    <div class="nv__tabs" role="tablist">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        class="nv__tab"
        :class="{ 'nv__tab--active': activeTab === tab.key }"
        role="tab"
        :aria-selected="activeTab === tab.key"
        type="button"
        @click="activeTab = tab.key"
      >
        {{ tab.label }}
      </button>
    </div>

    <!-- Skeleton -->
    <div v-if="store.loading && !store.loaded" class="nv__skeleton-list">
      <div v-for="n in 6" :key="n" class="nv__skeleton-row" />
    </div>

    <!-- Empty -->
    <div v-else-if="store.loaded && filtered.length === 0" class="nv__empty">
      <p>Nenhuma notificação aqui ainda.</p>
    </div>

    <!-- Groups -->
    <template v-else>
      <div v-for="group in grouped" :key="group.label" class="nv__group">
        <h2 class="nv__group-label">{{ group.label }}</h2>

        <ul class="nv__list">
          <li
            v-for="n in group.items"
            :key="n.id"
            class="nv__item"
            :class="{ 'nv__item--unread': !n.readAt }"
          >
            <!-- Avatar -->
            <component
              :is="profileLink(n) ? RouterLink : 'div'"
              v-bind="profileLink(n) ? { to: profileLink(n) } : {}"
              class="nv__avatar"
              :aria-label="n.data.actor_username ? `@${n.data.actor_username}` : undefined"
            >
              <img
                v-if="n.data.actor_avatar_url"
                class="nv__avatar-img"
                :src="n.data.actor_avatar_url"
                :alt="n.data.actor_username ?? ''"
              />
              <span v-else class="nv__avatar-initials">{{ avatarInitial(n.data.actor_username) }}</span>
            </component>

            <!-- Text -->
            <component
              :is="postLink(n) ? RouterLink : profileLink(n) ? RouterLink : 'div'"
              v-bind="postLink(n) ? { to: postLink(n) } : profileLink(n) ? { to: profileLink(n) } : {}"
              class="nv__body"
            >
              <span class="nv__text">{{ describeNotification(n) }}</span>
              <span class="nv__time">{{ timeAgo(n.createdAt) }}</span>
            </component>

            <!-- Right side: post thumb OR follow button OR accept/decline -->
            <div class="nv__right">
              <!-- Follow request: accept / decline -->
              <template v-if="n.type === 'follow_request'">
                <div class="nv__follow-actions">
                  <button
                    class="nv__btn nv__btn--accept"
                    type="button"
                    @click.prevent="store.acceptFollowRequest(n)"
                  >Confirmar</button>
                  <button
                    class="nv__btn nv__btn--decline"
                    type="button"
                    @click.prevent="store.declineFollowRequest(n)"
                  >Recusar</button>
                </div>
              </template>

              <!-- Follow: follow-back toggle -->
              <template v-else-if="n.type === 'follow'">
                <button
                  class="nv__btn"
                  :class="followingBack[n.data.actor_username] ? 'nv__btn--following' : 'nv__btn--follow'"
                  type="button"
                  @click="toggleFollow(n.data.actor_username)"
                >
                  {{ followingBack[n.data.actor_username] ? 'Seguindo' : 'Seguir de volta' }}
                </button>
              </template>

              <!-- Like / comment: post thumbnail -->
              <template v-else-if="n.data.post_image_url">
                <RouterLink
                  v-if="postLink(n)"
                  class="nv__thumb-link"
                  :to="postLink(n)"
                >
                  <video
                    v-if="n.data.post_is_video"
                    class="nv__thumb"
                    :src="n.data.post_image_url"
                    preload="metadata"
                    muted
                    playsinline
                  />
                  <img
                    v-else
                    class="nv__thumb"
                    :src="n.data.post_image_url"
                    alt=""
                  />
                </RouterLink>
                <div v-else class="nv__thumb-link">
                  <img class="nv__thumb" :src="n.data.post_image_url" alt="" />
                </div>
              </template>
            </div>
          </li>
        </ul>
      </div>
    </template>

    <!-- Load more -->
    <div v-if="store.hasMore" class="nv__more">
      <button
        class="nv__more-btn"
        type="button"
        :disabled="store.loading"
        @click="store.fetchList({ reset: false })"
      >
        {{ store.loading ? 'Carregando...' : 'Carregar mais' }}
      </button>
    </div>
  </section>
</template>

<style scoped>
.nv {
  max-width: 640px;
  margin: 0 auto;
  padding-bottom: 3rem;
}

/* ── Title ── */
.nv__title {
  margin: 0.75rem 0 1rem;
  font-size: clamp(1.4rem, 4vw, 2rem);
  font-weight: 800;
  color: var(--app-text);
}

/* ── Tabs ── */
.nv__tabs {
  display: flex;
  gap: 0;
  border-bottom: 1px solid var(--app-border);
  margin-bottom: 1.25rem;
}

.nv__tab {
  padding: 0.65rem 1.1rem;
  border: 0;
  border-bottom: 2px solid transparent;
  margin-bottom: -1px;
  background: none;
  color: var(--app-muted);
  font-size: 0.9rem;
  font-weight: 600;
  cursor: pointer;
  transition: color 150ms ease, border-color 150ms ease;
  white-space: nowrap;
}

.nv__tab:hover {
  color: var(--app-text);
}

.nv__tab--active {
  color: var(--app-text);
  border-bottom-color: var(--app-text);
}

/* ── Groups ── */
.nv__group {
  margin-bottom: 1.5rem;
}

.nv__group-label {
  margin: 0 0 0.5rem;
  font-size: 0.8rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--app-muted);
}

/* ── List ── */
.nv__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.nv__item {
  display: flex;
  align-items: center;
  gap: 0.85rem;
  padding: 0.7rem 0.85rem;
  border-radius: 0.85rem;
  transition: background 150ms ease;
}

.nv__item:hover {
  background: var(--app-surface-soft);
}

.nv__item--unread {
  background: color-mix(in srgb, var(--app-accent) 7%, transparent);
}

.nv__item--unread:hover {
  background: color-mix(in srgb, var(--app-accent) 12%, transparent);
}

/* ── Avatar ── */
.nv__avatar {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  flex-shrink: 0;
  overflow: hidden;
  background: var(--app-surface-soft);
  display: flex;
  align-items: center;
  justify-content: center;
  text-decoration: none;
  border: 1.5px solid var(--app-border);
}

.nv__avatar-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.nv__avatar-initials {
  font-size: 1.05rem;
  font-weight: 700;
  color: var(--app-muted);
  user-select: none;
}

/* ── Body ── */
.nv__body {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
  text-decoration: none;
  color: inherit;
}

.nv__text {
  font-size: 0.9rem;
  line-height: 1.45;
  color: var(--app-text);
  white-space: normal;
}

.nv__time {
  font-size: 0.78rem;
  color: var(--app-muted);
}

/* ── Right side ── */
.nv__right {
  flex-shrink: 0;
}

/* Post thumbnail */
.nv__thumb-link {
  display: block;
  width: 44px;
  height: 44px;
  border-radius: 0.4rem;
  overflow: hidden;
  background: #111;
}

.nv__thumb {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

/* Follow actions */
.nv__follow-actions {
  display: flex;
  gap: 0.4rem;
}

/* Buttons */
.nv__btn {
  padding: 0.45rem 0.9rem;
  border-radius: 0.55rem;
  font-size: 0.82rem;
  font-weight: 700;
  border: 0;
  cursor: pointer;
  white-space: nowrap;
  transition: opacity 150ms ease, background 150ms ease;
}

.nv__btn:hover {
  opacity: 0.85;
}

.nv__btn--follow {
  background: var(--app-accent);
  color: #fff;
}

.nv__btn--following {
  background: var(--app-surface-soft);
  color: var(--app-text);
  border: 1px solid var(--app-border);
}

.nv__btn--accept {
  background: var(--app-accent);
  color: #fff;
}

.nv__btn--decline {
  background: var(--app-surface-soft);
  color: var(--app-text);
  border: 1px solid var(--app-border);
}

/* ── Skeleton ── */
.nv__skeleton-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.nv__skeleton-row {
  height: 60px;
  border-radius: 0.85rem;
  background: var(--app-surface-soft);
  animation: pulse 1.4s ease-in-out infinite;
}

.nv__skeleton-row:nth-child(2) { animation-delay: 0.1s; }
.nv__skeleton-row:nth-child(3) { animation-delay: 0.2s; }
.nv__skeleton-row:nth-child(4) { animation-delay: 0.15s; }

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.4; }
}

/* ── Empty ── */
.nv__empty {
  padding: 3rem 1rem;
  text-align: center;
  color: var(--app-muted);
  font-size: 0.9rem;
}

/* ── Load more ── */
.nv__more {
  display: flex;
  justify-content: center;
  padding: 1.25rem 0 0.5rem;
}

.nv__more-btn {
  min-width: 14rem;
  padding: 0.8rem 1.1rem;
  border: 1px solid var(--app-border);
  border-radius: 0.8rem;
  color: var(--app-text);
  font-weight: 700;
  background: var(--app-surface-soft);
  transition: background 180ms ease, border-color 180ms ease;
}

.nv__more-btn:hover:not(:disabled) {
  border-color: var(--app-border-strong);
  background: #1b1b1b;
}

.nv__more-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>
