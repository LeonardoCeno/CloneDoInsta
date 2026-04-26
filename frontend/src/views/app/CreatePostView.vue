<script setup>
import { computed, onBeforeUnmount, reactive, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { POST_CAPTION_MAX_LENGTH, useFeed } from '@/composables/useFeed'
import { extractErrorMessage } from '@/services/api'
import { useAuth } from '@/composables/useAuth'
import MediaDisplay from '@/components/shared/MediaDisplay.vue'
import ProfileAvatar from '@/components/profile/ProfileAvatar.vue'

const ACCEPTED_TYPES = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif', 'video/mp4']
const MAX_IMAGE_BYTES = 10 * 1024 * 1024
const MAX_VIDEO_BYTES = 100 * 1024 * 1024

const router = useRouter()
const { currentUser } = useAuth()
const { createPost } = useFeed()

const step = ref('select') // 'select' | 'caption'
const form = reactive({ caption: '' })

const fileInput = ref(null)
const dropActive = ref(false)
const selectedFile = ref(null)
const previewUrl = ref('')
const isVideo = ref(false)
const isSubmitting = ref(false)
const errorMessage = ref('')
const publishedPost = ref(null)

const trimmedCaption = computed(() => form.caption.trim())
const captionLength = computed(() => form.caption.length)
const hasFile = computed(() => Boolean(selectedFile.value))
const canPublish = computed(() => Boolean(trimmedCaption.value) && !isSubmitting.value)

function revokePreview() {
  if (previewUrl.value?.startsWith('blob:')) URL.revokeObjectURL(previewUrl.value)
}

function clearFile() {
  revokePreview()
  selectedFile.value = null
  previewUrl.value = ''
  isVideo.value = false
  if (fileInput.value) fileInput.value.value = ''
}

function goBack() {
  if (step.value === 'caption') {
    step.value = 'select'
    clearFile()
    errorMessage.value = ''
    form.caption = ''
  } else {
    router.push({ name: 'feed' })
  }
}

function processFile(file) {
  if (!file) return

  errorMessage.value = ''

  if (!ACCEPTED_TYPES.includes(file.type)) {
    errorMessage.value = 'Formato não suportado. Use JPG, PNG, WEBP, GIF ou MP4.'
    return
  }

  const maxBytes = file.type === 'video/mp4' ? MAX_VIDEO_BYTES : MAX_IMAGE_BYTES
  if (file.size > maxBytes) {
    errorMessage.value = file.type === 'video/mp4'
      ? 'Vídeo muito grande. Limite: 100 MB.'
      : 'Imagem muito grande. Limite: 10 MB.'
    return
  }

  revokePreview()
  selectedFile.value = file
  isVideo.value = file.type === 'video/mp4'
  previewUrl.value = URL.createObjectURL(file)
  step.value = 'caption'
}

function handleFileInput(event) {
  processFile(event.target.files?.[0])
}

function handleDrop(event) {
  dropActive.value = false
  processFile(event.dataTransfer.files?.[0])
}

async function handlePublish() {
  if (!selectedFile.value || !trimmedCaption.value || isSubmitting.value) return

  errorMessage.value = ''
  isSubmitting.value = true

  try {
    const post = await createPost({
      image: selectedFile.value,
      caption: trimmedCaption.value,
    })
    publishedPost.value = post
    form.caption = ''
    clearFile()
    step.value = 'done'
  } catch (error) {
    errorMessage.value = extractErrorMessage(error, 'Não foi possível publicar o post agora.')
  } finally {
    isSubmitting.value = false
  }
}

onBeforeUnmount(revokePreview)
</script>

<template>
  <div class="cp">
    <div class="cp__card">

      <!-- Header -->
      <header class="cp__header">
        <button class="cp__nav-btn" type="button" @click="goBack">
          <template v-if="step === 'select'">Cancelar</template>
          <template v-else-if="step === 'done'">Fechar</template>
          <template v-else>← Voltar</template>
        </button>

        <span class="cp__title">
          <template v-if="step === 'select'">Criar nova publicação</template>
          <template v-else-if="step === 'done'">Publicado!</template>
          <template v-else>Criar nova publicação</template>
        </span>

        <button
          v-if="step === 'caption'"
          class="cp__nav-btn cp__nav-btn--action"
          type="button"
          :disabled="!canPublish"
          @click="handlePublish"
        >
          {{ isSubmitting ? 'Publicando...' : 'Compartilhar' }}
        </button>
        <span v-else class="cp__nav-spacer" />
      </header>

      <!-- Step: select -->
      <div v-if="step === 'select'" class="cp__select">
        <div
          class="cp__dropzone"
          :class="{ 'cp__dropzone--active': dropActive }"
          role="button"
          tabindex="0"
          aria-label="Selecionar mídia"
          @click="fileInput?.click()"
          @keydown.enter="fileInput?.click()"
          @keydown.space.prevent="fileInput?.click()"
          @dragover.prevent="dropActive = true"
          @dragleave="dropActive = false"
          @drop.prevent="handleDrop"
        >
          <svg class="cp__drop-icon" viewBox="0 0 96 96" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M62 14H22a8 8 0 0 0-8 8v40l16-16 12 12 8-8 28 28V22a8 8 0 0 0-8-8Z"/>
            <circle cx="32" cy="32" r="6"/>
          </svg>
          <p class="cp__drop-label">Arraste fotos e vídeos aqui</p>
          <p v-if="errorMessage" class="cp__drop-error">{{ errorMessage }}</p>
        </div>

        <input
          ref="fileInput"
          type="file"
          accept="image/jpeg,image/png,image/webp,image/gif,video/mp4"
          class="cp__file-input"
          @change="handleFileInput"
        />

        <button class="cp__select-btn" type="button" @click="fileInput?.click()">
          Selecionar do dispositivo
        </button>
      </div>

      <!-- Step: caption -->
      <div v-else-if="step === 'caption'" class="cp__layout">
        <!-- Media side -->
        <div class="cp__media-side">
          <MediaDisplay
            class="cp__media"
            :src="previewUrl"
            :alt="trimmedCaption || 'Preview'"
            :is-video="isVideo"
            :controls="true"
            :loop="true"
          />
        </div>

        <!-- Form side -->
        <div class="cp__form-side">
          <div class="cp__author">
            <ProfileAvatar
              :name="currentUser?.name"
              :username="currentUser?.username"
              :avatar-url="currentUser?.avatarUrl"
              :colors="currentUser?.colors"
              size="sm"
            />
            <span class="cp__author-name">{{ currentUser?.username }}</span>
          </div>

          <textarea
            v-model="form.caption"
            class="cp__caption"
            :maxlength="POST_CAPTION_MAX_LENGTH"
            placeholder="Escreva uma legenda..."
            rows="7"
          />

          <div class="cp__char-count">{{ captionLength }}/{{ POST_CAPTION_MAX_LENGTH }}</div>

          <p v-if="errorMessage" class="cp__error">{{ errorMessage }}</p>
        </div>
      </div>

      <!-- Step: done -->
      <div v-else-if="step === 'done'" class="cp__done">
        <svg class="cp__done-icon" viewBox="0 0 96 96" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="48" cy="48" r="40"/>
          <path d="M28 48l14 14 26-26"/>
        </svg>
        <p class="cp__done-label">Sua publicação foi compartilhada.</p>
        <div class="cp__done-actions">
          <RouterLink class="cp__done-link" :to="{ name: 'feed' }">Ver no feed</RouterLink>
          <RouterLink
            v-if="publishedPost"
            class="cp__done-link cp__done-link--primary"
            :to="{ name: 'post-detalhes', params: { postId: publishedPost.id } }"
          >
            Ver post
          </RouterLink>
        </div>
      </div>

    </div>
  </div>
</template>

<style scoped>
.cp {
  display: flex;
  justify-content: center;
  padding: 1rem 0 3rem;
  container-type: inline-size;
}

.cp__card {
  width: min(100cqi, 900px);
  border: 1px solid var(--app-border);
  border-radius: 0.85rem;
  overflow: hidden;
  background: var(--app-surface);
  min-height: 560px;
  display: flex;
  flex-direction: column;
}

/* ── Header ── */
.cp__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.85rem 1rem;
  border-bottom: 1px solid var(--app-border);
  flex-shrink: 0;
}

.cp__title {
  font-size: 1rem;
  font-weight: 700;
  color: var(--app-text);
}

.cp__nav-btn {
  font-size: 0.9rem;
  font-weight: 600;
  color: var(--app-text);
  background: none;
  border: 0;
  cursor: pointer;
  padding: 0;
  min-width: 80px;
  transition: color 150ms ease;
}

.cp__nav-btn:hover {
  color: var(--app-muted);
}

.cp__nav-btn--action {
  color: var(--app-accent);
  text-align: right;
}

.cp__nav-btn--action:hover:not(:disabled) {
  color: var(--app-accent-strong);
}

.cp__nav-btn--action:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.cp__nav-spacer {
  min-width: 80px;
}

/* ── Step: select ── */
.cp__select {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 1.5rem;
  padding: 3rem 2rem;
}

.cp__dropzone {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1rem;
  cursor: pointer;
  padding: 3rem 2rem;
  border-radius: 0.75rem;
  border: 1.5px dashed transparent;
  transition: border-color 180ms ease, background 180ms ease;
}

.cp__dropzone:hover,
.cp__dropzone--active {
  border-color: var(--app-border-strong);
  background: var(--app-surface-soft);
}

.cp__drop-icon {
  width: 5rem;
  height: 5rem;
  color: var(--app-text);
  opacity: 0.85;
}

.cp__drop-label {
  margin: 0;
  font-size: 1.3rem;
  color: var(--app-text);
}

.cp__drop-error {
  margin: 0;
  font-size: 0.88rem;
  color: var(--app-danger);
  font-weight: 500;
}

.cp__file-input {
  display: none;
}

.cp__select-btn {
  padding: 0.6rem 1.25rem;
  border-radius: 0.6rem;
  background: var(--app-accent);
  color: #fff;
  font-size: 0.9rem;
  font-weight: 700;
  border: 0;
  cursor: pointer;
  transition: background 150ms ease;
}

.cp__select-btn:hover {
  background: var(--app-accent-hover, #1877f2);
}

/* ── Step: caption ── */
.cp__layout {
  flex: 1;
  display: grid;
  grid-template-columns: 1fr 320px;
  min-height: 0;
}

.cp__media-side {
  background: #111;
  display: grid;
  place-items: center;
  min-height: 480px;
}

.cp__media {
  width: 100%;
  height: 100%;
}

.cp__form-side {
  display: flex;
  flex-direction: column;
  padding: 1.25rem;
  border-left: 1px solid var(--app-border);
  gap: 0;
  overflow-y: auto;
}

.cp__author {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 1rem;
}

.cp__author-name {
  font-size: 0.92rem;
  font-weight: 700;
  color: var(--app-text);
}

.cp__caption {
  flex: 1;
  width: 100%;
  border: 0;
  outline: 0;
  background: transparent;
  color: var(--app-text);
  font-family: inherit;
  font-size: 0.95rem;
  line-height: 1.6;
  resize: none;
  padding: 0;
}

.cp__caption::placeholder {
  color: var(--app-muted);
}

.cp__char-count {
  margin-top: auto;
  padding-top: 0.75rem;
  border-top: 1px solid var(--app-border);
  font-size: 0.78rem;
  color: var(--app-muted);
  text-align: right;
}

.cp__error {
  margin: 0.75rem 0 0;
  font-size: 0.85rem;
  color: var(--app-danger);
  font-weight: 500;
}

/* ── Step: done ── */
.cp__done {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 1.25rem;
  padding: 3rem 2rem;
}

.cp__done-icon {
  width: 5rem;
  height: 5rem;
  color: var(--app-text);
}

.cp__done-label {
  margin: 0;
  font-size: 1.1rem;
  color: var(--app-text);
  font-weight: 500;
}

.cp__done-actions {
  display: flex;
  gap: 1rem;
}

.cp__done-link {
  padding: 0.6rem 1.25rem;
  border-radius: 0.6rem;
  border: 1px solid var(--app-border-strong);
  color: var(--app-text);
  font-size: 0.9rem;
  font-weight: 600;
  text-decoration: none;
  transition: background 150ms ease;
}

.cp__done-link:hover {
  background: var(--app-surface-soft);
}

.cp__done-link--primary {
  background: var(--app-accent);
  border-color: var(--app-accent);
  color: #fff;
}

.cp__done-link--primary:hover {
  background: #1877f2;
  border-color: #1877f2;
}

/* ── Mobile ── */
@media (max-width: 680px) {
  .cp__layout {
    grid-template-columns: 1fr;
  }

  .cp__media-side {
    min-height: 300px;
  }

  .cp__form-side {
    border-left: 0;
    border-top: 1px solid var(--app-border);
  }
}
</style>
