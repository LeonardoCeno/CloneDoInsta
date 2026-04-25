<script setup>
import { onMounted, computed } from 'vue'
import { RouterLink } from 'vue-router'
import { useNotificationsStore } from '@/stores/notifications'

const store = useNotificationsStore()

const unread = computed(() => store.notifications.filter((n) => !n.readAt))
const read = computed(() => store.notifications.filter((n) => n.readAt))

function timeAgo(value) {
  if (!value) return ''
  const diffMs = Math.max(0, Date.now() - new Date(value).getTime())
  const minute = 60 * 1000
  const hour = 60 * minute
  const day = 24 * hour
  const week = 7 * day

  if (diffMs < minute) return 'agora'
  if (diffMs < hour) return `${Math.floor(diffMs / minute)} min`
  if (diffMs < day) return `${Math.floor(diffMs / hour)} h`
  if (diffMs < week) return `${Math.floor(diffMs / day)} d`

  return new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: 'short' }).format(
    new Date(value),
  )
}

function describeNotification(n) {
  const actor = n.data.actor_username ? `@${n.data.actor_username}` : 'Alguém'
  if (n.type === 'like') return `${actor} curtiu seu post.`
  if (n.type === 'comment') return `${actor} comentou no seu post.`
  if (n.type === 'follow') return `${actor} passou a te seguir.`
  return `${actor} interagiu com você.`
}

function notificationLink(n) {
  if ((n.type === 'like' || n.type === 'comment') && n.data.post_id) {
    return { name: 'post-detalhes', params: { postId: n.data.post_id } }
  }
  if (n.type === 'follow' && n.data.actor_username) {
    return { name: 'perfil', query: { user: n.data.actor_username } }
  }
  return null
}

function notificationIcon(type) {
  if (type === 'like') return '❤️'
  if (type === 'comment') return '💬'
  if (type === 'follow') return '👤'
  return '🔔'
}

onMounted(async () => {
  await store.fetchList({ reset: true })
  if (store.unreadCount > 0) {
    await store.markAllRead()
  }
})
</script>

<template>
  <section class="notif">
    <header class="notif__header card border-0">
      <div>
        <span class="notif__eyebrow">Atividade recente</span>
        <h2>Notificações</h2>
      </div>
      <button
        v-if="store.notifications.length > 0"
        class="notif__mark-btn"
        type="button"
        @click="store.markAllRead"
      >
        Marcar todas como lidas
      </button>
    </header>

    <!-- Loading skeleton -->
    <div v-if="store.loading && !store.loaded" class="notif__list">
      <div v-for="n in 5" :key="n" class="notif__skeleton" />
    </div>

    <!-- Unread -->
    <section v-if="unread.length > 0" class="notif__group">
      <h3 class="notif__group-title">Novas</h3>
      <ul class="notif__list">
        <li v-for="n in unread" :key="n.id" class="notif__item notif__item--unread">
          <component
            :is="notificationLink(n) ? RouterLink : 'div'"
            v-bind="notificationLink(n) ? { to: notificationLink(n) } : {}"
            class="notif__row"
          >
            <span class="notif__icon">{{ notificationIcon(n.type) }}</span>
            <span class="notif__text">{{ describeNotification(n) }}</span>
            <time class="notif__time" :datetime="n.createdAt">{{ timeAgo(n.createdAt) }}</time>
          </component>
        </li>
      </ul>
    </section>

    <!-- Read -->
    <section v-if="read.length > 0" class="notif__group">
      <h3 v-if="unread.length > 0" class="notif__group-title">Anteriores</h3>
      <ul class="notif__list">
        <li v-for="n in read" :key="n.id" class="notif__item">
          <component
            :is="notificationLink(n) ? RouterLink : 'div'"
            v-bind="notificationLink(n) ? { to: notificationLink(n) } : {}"
            class="notif__row"
          >
            <span class="notif__icon">{{ notificationIcon(n.type) }}</span>
            <span class="notif__text">{{ describeNotification(n) }}</span>
            <time class="notif__time" :datetime="n.createdAt">{{ timeAgo(n.createdAt) }}</time>
          </component>
        </li>
      </ul>
    </section>

    <!-- Empty -->
    <section
      v-if="store.loaded && store.notifications.length === 0"
      class="notif__empty card border-0"
    >
      <h3>Sem notificações</h3>
      <p>Quando alguém curtir, comentar ou te seguir, você verá aqui.</p>
    </section>

    <!-- Load more -->
    <div v-if="store.hasMore" class="notif__more">
      <button
        class="notif__more-btn"
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
.notif {
  display: grid;
  gap: 1rem;
}

.notif__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  flex-wrap: wrap;
  padding: 1.4rem;
  border-radius: 1.75rem;
  background: var(--app-surface);
}

.notif__eyebrow {
  display: inline-block;
  margin-bottom: 0.35rem;
  color: var(--app-accent-strong);
  font-size: 0.78rem;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.notif__header h2 {
  margin: 0;
  font-size: clamp(1.6rem, 4vw, 2.3rem);
  font-weight: 800;
}

.notif__mark-btn {
  padding: 0.55rem 1rem;
  border: 1px solid var(--app-border);
  border-radius: 0.75rem;
  color: var(--app-muted);
  font-size: 0.85rem;
  font-weight: 600;
  background: var(--app-surface-soft);
  cursor: pointer;
  transition: color 150ms ease, border-color 150ms ease;
  white-space: nowrap;
}

.notif__mark-btn:hover {
  color: var(--app-text);
  border-color: var(--app-border-strong);
}

.notif__group-title {
  margin: 0 0 0.5rem;
  padding: 0 0.25rem;
  color: var(--app-muted);
  font-size: 0.8rem;
  font-weight: 700;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.notif__group {
  display: grid;
  gap: 0.5rem;
}

.notif__list {
  display: grid;
  gap: 0.4rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.notif__item {
  border-radius: 1rem;
  background: var(--app-surface);
  overflow: hidden;
  transition: background 150ms ease;
}

.notif__item--unread {
  background: color-mix(in srgb, var(--app-link) 8%, var(--app-surface));
  border: 1px solid color-mix(in srgb, var(--app-link) 20%, transparent);
}

.notif__row {
  display: flex;
  align-items: center;
  gap: 0.85rem;
  padding: 0.95rem 1rem;
  color: inherit;
  text-decoration: none;
}

a.notif__row:hover {
  background: var(--app-surface-soft);
}

.notif__icon {
  font-size: 1.25rem;
  flex-shrink: 0;
  line-height: 1;
}

.notif__text {
  flex: 1;
  font-size: 0.93rem;
  line-height: 1.5;
  color: var(--app-text);
}

.notif__time {
  color: var(--app-muted);
  font-size: 0.78rem;
  white-space: nowrap;
  flex-shrink: 0;
}

/* Skeleton */
.notif__skeleton {
  height: 56px;
  border-radius: 1rem;
  background: var(--app-surface-soft);
  animation: pulse 1.4s ease-in-out infinite;
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.45; }
}

.notif__empty {
  padding: 2rem 1.4rem;
  border-radius: 1.75rem;
  background: var(--app-surface);
}

.notif__empty h3 {
  margin: 0 0 0.35rem;
  font-size: 1.25rem;
  font-weight: 700;
}

.notif__empty p {
  margin: 0;
  color: var(--app-muted);
  line-height: 1.65;
}

.notif__more {
  display: flex;
  justify-content: center;
  padding: 0.5rem 0;
}

.notif__more-btn {
  min-width: 14rem;
  padding: 0.8rem 1.1rem;
  border: 1px solid var(--app-border);
  border-radius: 0.8rem;
  color: var(--app-text);
  font-weight: 700;
  background: var(--app-surface-soft);
  transition: background-color 180ms ease, border-color 180ms ease;
}

.notif__more-btn:hover:not(:disabled) {
  border-color: var(--app-border-strong);
  background: #1b1b1b;
}

.notif__more-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>
