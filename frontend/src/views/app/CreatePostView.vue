<script setup>
import { computed, onBeforeUnmount, reactive, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { POST_CAPTION_MAX_LENGTH, useFeed } from '@/composables/useFeed'
import { extractErrorMessage } from '@/services/api'
import MediaDisplay from '@/components/shared/MediaDisplay.vue'

const ACCEPTED_TYPES = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif', 'video/mp4']
const MAX_IMAGE_BYTES = 10 * 1024 * 1024
const MAX_VIDEO_BYTES = 100 * 1024 * 1024

const router = useRouter()

const form = reactive({ caption: '' })

const fileInput = ref(null)
const selectedFile = ref(null)
const previewUrl = ref('')
const isVideo = ref(false)
const isSubmitting = ref(false)
const errorMessage = ref('')
const successMessage = ref('')
const publishedPost = ref(null)

const { createPost } = useFeed()

const trimmedCaption = computed(() => form.caption.trim())
const captionLength = computed(() => form.caption.length)
const hasSelectedFile = computed(() => Boolean(selectedFile.value))
const publishButtonLabel = computed(() => (isSubmitting.value ? 'Publicando...' : 'Publicar'))
const canPublish = computed(
  () => Boolean(hasSelectedFile.value && trimmedCaption.value) && !isSubmitting.value,
)

function revokePreview() {
  if (previewUrl.value?.startsWith('blob:')) URL.revokeObjectURL(previewUrl.value)
}

function clearFeedback() {
  errorMessage.value = ''
  successMessage.value = ''
  publishedPost.value = null
}

function clearSelectedFile() {
  revokePreview()
  selectedFile.value = null
  previewUrl.value = ''
  isVideo.value = false
  if (fileInput.value) fileInput.value.value = ''
}

function handleFileChange(event) {
  const file = event.target.files?.[0]
  if (!file) return

  clearFeedback()

  if (!ACCEPTED_TYPES.includes(file.type)) {
    clearSelectedFile()
    errorMessage.value = 'Formato não suportado. Use JPG, PNG, WEBP, GIF ou MP4.'
    return
  }

  const maxBytes = file.type === 'video/mp4' ? MAX_VIDEO_BYTES : MAX_IMAGE_BYTES
  if (file.size > maxBytes) {
    clearSelectedFile()
    errorMessage.value = file.type === 'video/mp4'
      ? 'Vídeo muito grande. Limite: 100 MB.'
      : 'Imagem muito grande. Limite: 10 MB.'
    return
  }

  revokePreview()
  selectedFile.value = file
  isVideo.value = file.type === 'video/mp4'
  previewUrl.value = URL.createObjectURL(file)
}

function openFilePicker() {
  if (!hasSelectedFile.value) fileInput.value?.click()
}

async function handleSubmit() {
  clearFeedback()

  if (!selectedFile.value) {
    errorMessage.value = 'Selecione uma imagem ou vídeo.'
    return
  }

  if (!trimmedCaption.value) {
    errorMessage.value = 'Escreva uma legenda.'
    return
  }

  isSubmitting.value = true

  try {
    const post = await createPost({
      image: selectedFile.value,
      caption: trimmedCaption.value,
    })

    publishedPost.value = post
    successMessage.value = 'Post publicado com sucesso.'
    form.caption = ''
    clearSelectedFile()
  } catch (error) {
    errorMessage.value = extractErrorMessage(error, 'Não foi possível publicar o post agora.')
  } finally {
    isSubmitting.value = false
  }
}

onBeforeUnmount(() => {
  revokePreview()
})
</script>

<template>
  <section class="cp">
    <header class="cp__header">
      <h1 class="cp__title">Nova publicação</h1>
    </header>

    <p v-if="errorMessage" class="cp__feedback cp__feedback--error" role="alert">
      {{ errorMessage }}
    </p>
    <p v-if="successMessage" class="cp__feedback cp__feedback--success" role="status">
      {{ successMessage }}
    </p>

    <div class="cp__body">
      <!-- Media zone -->
      <div
        class="cp__zone"
        :class="{ 'cp__zone--filled': hasSelectedFile }"
        role="button"
        tabindex="0"
        aria-label="Selecionar mídia"
        @click="openFilePicker"
        @keydown.enter="openFilePicker"
        @keydown.space.prevent="openFilePicker"
      >
        <input
          ref="fileInput"
          type="file"
          accept="image/jpeg,image/png,image/webp,image/gif,video/mp4"
          class="cp__file-input"
          @change="handleFileChange"
        />

        <MediaDisplay
          v-if="hasSelectedFile"
          class="cp__media"
          :src="previewUrl"
          :alt="trimmedCaption || 'Preview'"
          :is-video="isVideo"
          :controls="true"
          :loop="true"
        />

        <div v-else class="cp__placeholder">
          <svg class="cp__upload-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
          </svg>
          <span class="cp__placeholder-label">Selecionar foto ou vídeo</span>
        </div>

        <button
          v-if="hasSelectedFile"
          class="cp__change-btn"
          type="button"
          @click.stop="fileInput?.click()"
        >
          Trocar
        </button>
      </div>

      <!-- Form -->
      <form class="cp__form" novalidate @submit.prevent="handleSubmit">
        <div class="cp__caption-wrap">
          <textarea
            id="post-caption"
            v-model="form.caption"
            class="cp__caption"
            :maxlength="POST_CAPTION_MAX_LENGTH"
            placeholder="Escreva uma legenda..."
            rows="5"
          />
          <span class="cp__char-count">{{ captionLength }}/{{ POST_CAPTION_MAX_LENGTH }}</span>
        </div>

        <div class="cp__actions">
          <button
            class="cp__publish"
            type="submit"
            :disabled="!canPublish"
          >
            {{ publishButtonLabel }}
          </button>

          <button
            v-if="hasSelectedFile"
            class="cp__secondary"
            type="button"
            @click="clearSelectedFile"
          >
            Remover mídia
          </button>

          <RouterLink
            v-if="publishedPost"
            class="cp__secondary"
            :to="{ name: 'post-detalhes', params: { postId: publishedPost.id } }"
          >
            Ver post
          </RouterLink>

          <button
            v-if="publishedPost"
            class="cp__secondary"
            type="button"
            @click="router.push({ name: 'feed' })"
          >
            Ir para o feed
          </button>
        </div>
      </form>
    </div>
  </section>
</template>

<style scoped>
.cp {
  max-width: 680px;
}

.cp__header {
  margin-bottom: 1.75rem;
}

.cp__title {
  margin: 0;
  font-size: clamp(1.4rem, 4vw, 1.9rem);
  font-weight: 700;
  color: var(--app-text);
}

.cp__feedback {
  margin: 0 0 1.25rem;
  padding: 0.8rem 1rem;
  border-radius: 0.75rem;
  font-size: 0.9rem;
  font-weight: 500;
}

.cp__feedback--error {
  color: #ffb4ba;
  background: rgba(255, 48, 64, 0.08);
  border: 1px solid rgba(255, 48, 64, 0.24);
}

.cp__feedback--success {
  color: var(--app-success);
  background: rgba(66, 211, 146, 0.08);
  border: 1px solid rgba(66, 211, 146, 0.24);
}

.cp__body {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.5rem;
  align-items: start;
}

/* ── Media zone ── */
.cp__zone {
  position: relative;
  aspect-ratio: 1 / 1;
  border-radius: 0.85rem;
  border: 1.5px dashed var(--app-border-strong);
  background: var(--app-surface);
  overflow: hidden;
  cursor: pointer;
  transition: border-color 180ms ease, background 180ms ease;
}

.cp__zone:hover,
.cp__zone:focus-visible {
  border-color: var(--app-accent);
  background: var(--app-surface-soft);
  outline: none;
}

.cp__zone--filled {
  border-style: solid;
  cursor: default;
}

.cp__file-input {
  display: none;
}

.cp__media {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.cp__placeholder {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.85rem;
  color: var(--app-muted);
}

.cp__upload-icon {
  width: 2.25rem;
  height: 2.25rem;
  opacity: 0.6;
}

.cp__placeholder-label {
  font-size: 0.9rem;
  font-weight: 500;
}

.cp__change-btn {
  position: absolute;
  bottom: 0.75rem;
  right: 0.75rem;
  padding: 0.3rem 0.75rem;
  border: 0;
  border-radius: 0.5rem;
  background: rgba(0, 0, 0, 0.65);
  color: #fff;
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
  transition: background 150ms ease;
}

.cp__change-btn:hover {
  background: rgba(0, 0, 0, 0.85);
}

/* ── Form ── */
.cp__form {
  display: grid;
  gap: 1.25rem;
}

.cp__caption-wrap {
  position: relative;
}

.cp__caption {
  width: 100%;
  padding: 0.85rem 1rem;
  border: 1px solid var(--app-border);
  border-radius: 0.75rem;
  background: var(--app-surface-soft);
  color: var(--app-text);
  font-size: 0.95rem;
  line-height: 1.6;
  resize: none;
  transition: border-color 180ms ease;
}

.cp__caption:focus {
  outline: none;
  border-color: rgba(0, 149, 246, 0.55);
  box-shadow: 0 0 0 0.2rem rgba(0, 149, 246, 0.14);
}

.cp__caption::placeholder {
  color: #6f6f6f;
}

.cp__char-count {
  position: absolute;
  bottom: 0.6rem;
  right: 0.75rem;
  font-size: 0.75rem;
  color: var(--app-muted);
  pointer-events: none;
}

.cp__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
}

.cp__publish {
  flex: 1;
  min-width: 0;
  padding: 0.75rem 1rem;
  border: 0;
  border-radius: 0.75rem;
  background: var(--app-accent);
  color: #fff;
  font-size: 0.95rem;
  font-weight: 700;
  cursor: pointer;
  transition: opacity 150ms ease, background 150ms ease;
}

.cp__publish:hover:not(:disabled) {
  background: #1877f2;
}

.cp__publish:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.cp__secondary {
  padding: 0.75rem 1rem;
  border: 1px solid var(--app-border-strong);
  border-radius: 0.75rem;
  background: transparent;
  color: var(--app-text);
  font-size: 0.9rem;
  font-weight: 600;
  text-decoration: none;
  cursor: pointer;
  transition: background 150ms ease;
}

.cp__secondary:hover {
  background: var(--app-surface-soft);
}

@media (max-width: 680px) {
  .cp__body {
    grid-template-columns: 1fr;
  }
}
</style>
