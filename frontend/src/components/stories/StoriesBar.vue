<script setup>
import { computed, ref } from 'vue'
import ProfileAvatar from '@/components/profile/ProfileAvatar.vue'
import AppIcon from '@/components/layout/AppIcon.vue'
import { useStoriesStore } from '@/stores/stories'
import { useAuth } from '@/composables/useAuth'

const storiesStore = useStoriesStore()
const { currentUser } = useAuth()

const fileInput = ref(null)
const uploading = ref(false)

const ownGroup = computed(() =>
  storiesStore.groups.find((g) => g.user.id === currentUser.value?.id) ?? null,
)

const otherGroups = computed(() =>
  storiesStore.groups.filter((g) => g.user.id !== currentUser.value?.id),
)

const ownGroupIdx = computed(() =>
  storiesStore.groups.findIndex((g) => g.user.id === currentUser.value?.id),
)

function handleOwnCircleClick() {
  if (ownGroup.value?.stories.length) {
    storiesStore.openViewer(ownGroupIdx.value, 0)
  } else {
    fileInput.value?.click()
  }
}

function handleAddClick(e) {
  e.stopPropagation()
  fileInput.value?.click()
}

async function handleFileChange(e) {
  const file = e.target.files?.[0]
  if (!file || uploading.value) return

  uploading.value = true
  try {
    await storiesStore.createStory(file, currentUser.value?.id)
  } finally {
    uploading.value = false
    e.target.value = ''
  }
}

function openGroup(groupIdx) {
  storiesStore.openViewer(groupIdx, 0)
}

function getRealGroupIdx(otherGroupUser) {
  return storiesStore.groups.findIndex((g) => g.user.id === otherGroupUser.id)
}
</script>

<template>
  <div class="stories-bar" role="list" aria-label="Stories">
    <!-- Hidden file input -->
    <input
      ref="fileInput"
      type="file"
      accept="image/jpeg,image/jpg,image/png,image/webp"
      class="stories-bar__file-input"
      @change="handleFileChange"
    />

    <!-- Own story circle -->
    <div class="stories-bar__item" role="listitem">
      <button
        class="stories-bar__btn"
        :class="{ 'is-uploading': uploading }"
        type="button"
        :aria-label="ownGroup ? 'Ver seu story' : 'Criar story'"
        @click="handleOwnCircleClick"
      >
        <div
          class="story-circle"
          :class="{
            'story-circle--unseen': ownGroup?.hasUnseen,
            'story-circle--seen': ownGroup && !ownGroup.hasUnseen,
          }"
        >
          <div class="story-circle__inner">
            <ProfileAvatar
              :name="currentUser?.name"
              :username="currentUser?.username"
              :avatar-url="currentUser?.avatarUrl"
              :colors="currentUser?.colors"
              size="sm"
            />
          </div>
        </div>
        <span
          class="stories-bar__add"
          :class="{ 'is-hidden': uploading }"
          :title="ownGroup ? 'Adicionar story' : 'Criar story'"
          @click.stop="handleAddClick"
        >
          <AppIcon name="plus" />
        </span>
      </button>
      <span class="stories-bar__label">{{ uploading ? '...' : 'Seu story' }}</span>
    </div>

    <!-- Other users -->
    <div
      v-for="group in otherGroups"
      :key="group.user.id"
      class="stories-bar__item"
      role="listitem"
    >
      <button
        class="stories-bar__btn"
        type="button"
        :aria-label="`Story de ${group.user.username}`"
        @click="openGroup(getRealGroupIdx(group.user))"
      >
        <div
          class="story-circle"
          :class="{
            'story-circle--unseen': group.hasUnseen,
            'story-circle--seen': !group.hasUnseen,
          }"
        >
          <div class="story-circle__inner">
            <ProfileAvatar
              :name="group.user.name"
              :username="group.user.username"
              :avatar-url="group.user.avatarUrl"
              :colors="group.user.colors"
              size="sm"
            />
          </div>
        </div>
      </button>
      <span class="stories-bar__label">{{ group.user.username }}</span>
    </div>
  </div>
</template>

<style scoped>
.stories-bar {
  display: flex;
  align-items: flex-start;
  gap: 1rem;
  padding: 0.85rem 1rem;
  overflow-x: auto;
  scrollbar-width: none;
  border-radius: 0.9rem;
  background: var(--app-surface);
}

.stories-bar::-webkit-scrollbar {
  display: none;
}

.stories-bar__file-input {
  display: none;
}

.stories-bar__item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.4rem;
  flex-shrink: 0;
}

.stories-bar__btn {
  position: relative;
  padding: 0;
  border: 0;
  background: none;
  cursor: pointer;
}

.stories-bar__btn.is-uploading {
  opacity: 0.5;
  pointer-events: none;
}

.story-circle {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 64px;
  height: 64px;
  border-radius: 50%;
  padding: 3px;
  background: var(--app-border);
  transition: opacity 150ms ease;
}

.story-circle--unseen {
  background: linear-gradient(135deg, #f09433 0%, #e6683c 20%, #dc2743 40%, #cc2366 70%, #bc1888 100%);
}

.story-circle--seen {
  background: var(--app-border);
}

.story-circle__inner {
  width: 100%;
  height: 100%;
  border-radius: 50%;
  border: 3px solid var(--app-surface);
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
}

.stories-bar__add {
  position: absolute;
  bottom: 0;
  right: 0;
  display: grid;
  place-items: center;
  width: 22px;
  height: 22px;
  border-radius: 50%;
  background: var(--app-link);
  color: #fff;
  border: 2px solid var(--app-surface);
  cursor: pointer;
  transition: transform 150ms ease;
}

.stories-bar__add .app-icon {
  width: 12px;
  height: 12px;
}

.stories-bar__add:hover {
  transform: scale(1.1);
}

.stories-bar__add.is-hidden {
  display: none;
}

.stories-bar__label {
  max-width: 64px;
  color: var(--app-text);
  font-size: 0.72rem;
  text-align: center;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.stories-bar__btn:hover .story-circle,
.stories-bar__btn:focus-visible .story-circle {
  opacity: 0.85;
}
</style>
