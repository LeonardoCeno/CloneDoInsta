<script setup>
import { ref, computed, watch, onUnmounted } from 'vue'
import { useStoriesStore } from '@/stores/stories'
import { useAuth } from '@/composables/useAuth'
import ProfileAvatar from '@/components/profile/ProfileAvatar.vue'
import AppIcon from '@/components/layout/AppIcon.vue'
import MediaDisplay from '@/components/shared/MediaDisplay.vue'

const STORY_DURATION = 5000

const storiesStore = useStoriesStore()
const { currentUser } = useAuth()

const progress = ref(0)
let rafId = null
let startTime = null

const currentGroup = computed(() => storiesStore.currentGroup)
const currentStory = computed(() => storiesStore.currentStory)
const prevGroup = computed(() => storiesStore.prevGroup)
const nextGroup = computed(() => storiesStore.nextGroup)

const isOwn = computed(
  () => currentGroup.value?.user.id === currentUser.value?.id,
)

const timeAgo = computed(() => {
  if (!currentStory.value?.createdAt) return ''
  const diffMs = Math.max(0, Date.now() - new Date(currentStory.value.createdAt).getTime())
  const minute = 60 * 1000
  const hour = 60 * minute
  if (diffMs < hour) return `${Math.max(1, Math.floor(diffMs / minute))} min`
  return `${Math.floor(diffMs / hour)} h`
})

function barStyle(idx) {
  const storyIdx = storiesStore.viewerStoryIdx
  if (idx < storyIdx) return { width: '100%' }
  if (idx === storyIdx) return { width: `${progress.value}%` }
  return { width: '0%' }
}

function startProgress() {
  stopProgress()
  startTime = performance.now()
  progress.value = 0

  function tick(now) {
    const elapsed = now - startTime
    progress.value = Math.min((elapsed / STORY_DURATION) * 100, 100)

    if (elapsed >= STORY_DURATION) {
      storiesStore.goNext()
    } else {
      rafId = requestAnimationFrame(tick)
    }
  }

  rafId = requestAnimationFrame(tick)
}

function stopProgress() {
  if (rafId) {
    cancelAnimationFrame(rafId)
    rafId = null
  }
  progress.value = 0
}

async function onStoryVisible(story) {
  if (story && !story.seenByMe) {
    await storiesStore.markSeen(story.id)
  }
}

function handleNavClick(e) {
  const rect = e.currentTarget.getBoundingClientRect()
  const x = e.clientX - rect.left
  if (x < rect.width / 2) {
    storiesStore.goPrev()
  } else {
    storiesStore.goNext()
  }
}

function handleKeydown(e) {
  if (!storiesStore.viewerOpen) return
  if (e.key === 'Escape') storiesStore.closeViewer()
  if (e.key === 'ArrowRight') storiesStore.goNext()
  if (e.key === 'ArrowLeft') storiesStore.goPrev()
}

async function handleDelete() {
  if (!currentStory.value) return
  stopProgress()
  await storiesStore.deleteStory(currentStory.value.id)
  if (!storiesStore.currentStory) {
    storiesStore.closeViewer()
  }
}

watch(
  [() => storiesStore.viewerOpen, () => storiesStore.viewerGroupIdx, () => storiesStore.viewerStoryIdx],
  ([open]) => {
    if (!open) {
      stopProgress()
      return
    }
    onStoryVisible(currentStory.value)
    startProgress()
  },
)

watch(
  () => storiesStore.viewerOpen,
  (open) => {
    if (open) {
      document.addEventListener('keydown', handleKeydown)
      document.body.style.overflow = 'hidden'
    } else {
      document.removeEventListener('keydown', handleKeydown)
      document.body.style.overflow = ''
    }
  },
)

onUnmounted(() => {
  stopProgress()
  document.removeEventListener('keydown', handleKeydown)
  document.body.style.overflow = ''
})
</script>

<template>
  <Teleport to="body">
    <Transition name="viewer">
      <div
        v-if="storiesStore.viewerOpen && currentGroup"
        class="story-viewer"
        role="dialog"
        aria-modal="true"
        aria-label="Visualizador de stories"
        @click.self="storiesStore.closeViewer"
      >
        <!-- Previous group (left) -->
        <div
          v-if="prevGroup"
          class="story-viewer__side story-viewer__side--left"
          role="button"
          tabindex="0"
          :aria-label="`Story de ${prevGroup.user.username}`"
          @click="storiesStore.goTo(storiesStore.viewerGroupIdx - 1, 0)"
          @keydown.enter="storiesStore.goTo(storiesStore.viewerGroupIdx - 1, 0)"
        >
          <div class="story-viewer__side-card">
            <MediaDisplay
              v-if="prevGroup.stories[0]"
              :src="prevGroup.stories[0].imageUrl"
              :is-video="prevGroup.stories[0].isVideo"
              :thumbnail="true"
              class="story-viewer__side-img"
            />
            <div class="story-viewer__side-user">
              <ProfileAvatar
                :name="prevGroup.user.name"
                :username="prevGroup.user.username"
                :avatar-url="prevGroup.user.avatarUrl"
                :colors="prevGroup.user.colors"
                size="sm"
              />
              <span>{{ prevGroup.user.username }}</span>
            </div>
          </div>
        </div>

        <!-- Main story card -->
        <div class="story-viewer__card">
          <!-- Progress bars -->
          <div class="story-viewer__progress" aria-hidden="true">
            <div
              v-for="(story, i) in currentGroup.stories"
              :key="story.id"
              class="story-viewer__bar"
            >
              <div class="story-viewer__bar-fill" :style="barStyle(i)" />
            </div>
          </div>

          <!-- Header -->
          <header class="story-viewer__header">
            <div class="story-viewer__user">
              <div class="story-viewer__avatar">
                <ProfileAvatar
                  :name="currentGroup.user.name"
                  :username="currentGroup.user.username"
                  :avatar-url="currentGroup.user.avatarUrl"
                  :colors="currentGroup.user.colors"
                  size="sm"
                />
              </div>
              <div class="story-viewer__user-meta">
                <strong>{{ currentGroup.user.username }}</strong>
                <span>{{ timeAgo }}</span>
              </div>
            </div>

            <div class="story-viewer__actions">
              <button
                v-if="isOwn"
                class="story-viewer__icon-btn story-viewer__delete"
                type="button"
                title="Remover story"
                @click="handleDelete"
              >
                <AppIcon name="more" />
              </button>
              <button
                class="story-viewer__icon-btn"
                type="button"
                title="Fechar"
                aria-label="Fechar story"
                @click="storiesStore.closeViewer"
              >
                <AppIcon name="close" />
              </button>
            </div>
          </header>

          <!-- Story image with tap zones -->
          <div class="story-viewer__media" @click="handleNavClick">
            <MediaDisplay
              v-if="currentStory"
              :key="currentStory.id"
              :src="currentStory.imageUrl"
              :is-video="currentStory.isVideo"
              :autoplay="true"
              :loop="false"
              :muted="false"
              :controls="false"
              class="story-viewer__img"
            />
          </div>
        </div>

        <!-- Next group (right) -->
        <div
          v-if="nextGroup"
          class="story-viewer__side story-viewer__side--right"
          role="button"
          tabindex="0"
          :aria-label="`Story de ${nextGroup.user.username}`"
          @click="storiesStore.goTo(storiesStore.viewerGroupIdx + 1, 0)"
          @keydown.enter="storiesStore.goTo(storiesStore.viewerGroupIdx + 1, 0)"
        >
          <div class="story-viewer__side-card">
            <img
              v-if="nextGroup.stories[0]"
              :src="nextGroup.stories[0].imageUrl"
              class="story-viewer__side-img"
              alt=""
            />
            <div class="story-viewer__side-user">
              <ProfileAvatar
                :name="nextGroup.user.name"
                :username="nextGroup.user.username"
                :avatar-url="nextGroup.user.avatarUrl"
                :colors="nextGroup.user.colors"
                size="sm"
              />
              <span>{{ nextGroup.user.username }}</span>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.story-viewer {
  position: fixed;
  inset: 0;
  z-index: 1000;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 1.5rem;
  background: rgba(0, 0, 0, 0.92);
  padding: 1rem;
}

/* Main card */
.story-viewer__card {
  position: relative;
  display: flex;
  flex-direction: column;
  width: 100%;
  max-width: 390px;
  height: min(calc(390px * (16 / 9)), 85dvh);
  border-radius: 1.25rem;
  overflow: hidden;
  background: #111;
  flex-shrink: 0;
  box-shadow: 0 8px 48px rgba(0, 0, 0, 0.7);
}

/* Progress bars */
.story-viewer__progress {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  z-index: 10;
  display: flex;
  gap: 3px;
  padding: 0.6rem 0.65rem 0;
}

.story-viewer__bar {
  flex: 1;
  height: 3px;
  border-radius: 2px;
  background: rgba(255, 255, 255, 0.35);
  overflow: hidden;
}

.story-viewer__bar-fill {
  height: 100%;
  background: #fff;
  border-radius: 2px;
  transition: width 50ms linear;
}

/* Header */
.story-viewer__header {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  z-index: 10;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 1.4rem 0.75rem 0.75rem;
  background: linear-gradient(to bottom, rgba(0, 0, 0, 0.55) 0%, transparent 100%);
}

.story-viewer__user {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  min-width: 0;
}

.story-viewer__avatar {
  flex-shrink: 0;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  border: 2px solid rgba(255, 255, 255, 0.8);
  overflow: hidden;
}

.story-viewer__user-meta {
  display: grid;
  min-width: 0;
}

.story-viewer__user-meta strong {
  color: #fff;
  font-size: 0.88rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.story-viewer__user-meta span {
  color: rgba(255, 255, 255, 0.7);
  font-size: 0.75rem;
}

.story-viewer__actions {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  flex-shrink: 0;
}

.story-viewer__icon-btn {
  display: grid;
  place-items: center;
  width: 2rem;
  height: 2rem;
  padding: 0;
  border: 0;
  border-radius: 50%;
  color: #fff;
  background: none;
  cursor: pointer;
  transition: background 150ms ease;
}

.story-viewer__icon-btn:hover {
  background: rgba(255, 255, 255, 0.15);
}

.story-viewer__icon-btn .app-icon {
  width: 18px;
  height: 18px;
}

/* Media area */
.story-viewer__media {
  position: absolute;
  inset: 0;
  cursor: pointer;
}

.story-viewer__img {
  width: 100%;
  height: 100%;
  object-fit: contain;
  display: block;
}

/* Side previews */
.story-viewer__side {
  display: none;
  cursor: pointer;
}

.story-viewer__side-card {
  position: relative;
  width: 120px;
  height: 213px;
  border-radius: 0.75rem;
  overflow: hidden;
  background: #222;
  box-shadow: 0 4px 24px rgba(0, 0, 0, 0.5);
  transition: transform 200ms ease, opacity 200ms ease;
  opacity: 0.65;
}

.story-viewer__side:hover .story-viewer__side-card {
  opacity: 0.85;
  transform: scale(1.02);
}

.story-viewer__side-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.story-viewer__side-user {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  display: flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.5rem;
  background: linear-gradient(transparent, rgba(0, 0, 0, 0.7));
}

.story-viewer__side-user span {
  color: #fff;
  font-size: 0.7rem;
  font-weight: 600;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* Transitions */
.viewer-enter-active,
.viewer-leave-active {
  transition: opacity 200ms ease;
}

.viewer-enter-from,
.viewer-leave-to {
  opacity: 0;
}

/* Show side previews on wider screens */
@media (min-width: 700px) {
  .story-viewer__side {
    display: block;
  }
}
</style>
