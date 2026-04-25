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
const selectedFileName = ref('')
const isVideo = ref(false)
const isSubmitting = ref(false)
const errorMessage = ref('')
const successMessage = ref('')
const publishedPost = ref(null)

const { createPost } = useFeed()

const trimmedCaption = computed(() => form.caption.trim())
const captionLength = computed(() => form.caption.length)
const hasSelectedFile = computed(() => Boolean(selectedFile.value))
const uploadStateLabel = computed(() =>
  hasSelectedFile.value
    ? `${isVideo.value ? 'Vídeo' : 'Imagem'} pronto para publicação.`
    : 'Aceita JPG, PNG, WEBP ou MP4 (vídeo até 100 MB).',
)
const publishButtonLabel = computed(() => (isSubmitting.value ? 'Publicando...' : 'Publicar post'))
const canPublish = computed(
  () => Boolean(hasSelectedFile.value && trimmedCaption.value) && !isSubmitting.value,
)
const previewAlt = computed(() =>
  trimmedCaption.value
    ? `Preview do post com a legenda: ${trimmedCaption.value}`
    : 'Preview da mídia selecionada para o post',
)
const previewCaption = computed(
  () => trimmedCaption.value || 'Sua legenda aparece aqui assim que você começar a escrever.',
)

function revokePreview() {
  if (previewUrl.value?.startsWith('blob:')) URL.revokeObjectURL(previewUrl.value)
}

function clearFeedback() {
  errorMessage.value = ''
  successMessage.value = ''
  publishedPost.value = null
}

function handleDraftInput() {
  if (successMessage.value || publishedPost.value) {
    successMessage.value = ''
    publishedPost.value = null
  }
  if (errorMessage.value) errorMessage.value = ''
}

function clearSelectedFile() {
  revokePreview()
  selectedFile.value = null
  previewUrl.value = ''
  selectedFileName.value = ''
  isVideo.value = false
  if (fileInput.value) fileInput.value.value = ''
}

function handleFileChange(event) {
  const file = event.target.files?.[0]
  if (!file) return

  clearFeedback()

  if (!ACCEPTED_TYPES.includes(file.type)) {
    clearSelectedFile()
    errorMessage.value = 'Use JPG, PNG, WEBP, GIF ou MP4.'
    return
  }

  const maxBytes = file.type === 'video/mp4' ? MAX_VIDEO_BYTES : MAX_IMAGE_BYTES
  if (file.size > maxBytes) {
    clearSelectedFile()
    errorMessage.value = file.type === 'video/mp4'
      ? 'Vídeo deve ter no máximo 100 MB.'
      : 'Imagem deve ter no máximo 10 MB.'
    return
  }

  revokePreview()
  selectedFile.value = file
  selectedFileName.value = file.name
  isVideo.value = file.type === 'video/mp4'
  previewUrl.value = URL.createObjectURL(file)
}

async function handleSubmit() {
  clearFeedback()

  if (!selectedFile.value) {
    errorMessage.value = 'Selecione uma imagem ou vídeo antes de publicar.'
    return
  }

  if (!trimmedCaption.value) {
    errorMessage.value = 'Escreva uma legenda antes de publicar.'
    return
  }

  isSubmitting.value = true

  try {
    const post = await createPost({
      image: selectedFile.value,
      caption: trimmedCaption.value,
    })

    publishedPost.value = post
    successMessage.value = 'Post publicado com sucesso. Ele já está no topo do seu feed.'
    form.caption = ''
    clearSelectedFile()
  } catch (error) {
    errorMessage.value = extractErrorMessage(error, 'Não foi possível publicar o post agora.')
  } finally {
    isSubmitting.value = false
  }
}

function goToFeed() {
  router.push({ name: 'feed' })
}

onBeforeUnmount(() => {
  revokePreview()
})
</script>

<template>
  <section class="create-post">
    <section class="create-post__hero card border-0">
      <div class="create-post__hero-copy">
        <span class="create-post__eyebrow">Nova publicação</span>
        <h2>Monte seu próximo post antes de soltar no feed.</h2>
        <p>
          Envie uma imagem ou vídeo MP4, escreva a legenda e publique direto na sua timeline.
        </p>
      </div>

      <ul class="create-post__hero-facts">
        <li>Preview instantâneo da mídia selecionada</li>
        <li>Legenda com limite de {{ POST_CAPTION_MAX_LENGTH }} caracteres</li>
        <li>Imagens até 10 MB · Vídeos MP4 até 100 MB</li>
      </ul>
    </section>

    <p v-if="errorMessage" class="create-post__feedback is-error" role="alert">
      {{ errorMessage }}
    </p>

    <p v-if="successMessage" class="create-post__feedback is-success" role="status">
      {{ successMessage }}
    </p>

    <section class="create-post__grid">
      <article class="create-post__preview card border-0">
        <div class="create-post__frame" :class="{ 'has-image': hasSelectedFile }">
          <MediaDisplay
            v-if="hasSelectedFile"
            :src="previewUrl"
            :alt="previewAlt"
            :is-video="isVideo"
            :controls="true"
            :loop="true"
          />

          <div v-else class="create-post__placeholder">
            <strong>Sua mídia aparece aqui</strong>
            <p>
              Escolha um arquivo para visualizar o enquadramento e preparar a legenda antes da
              publicação.
            </p>
          </div>
        </div>

        <div class="create-post__preview-copy">
          <span class="create-post__preview-label">
            {{ hasSelectedFile ? 'Preview pronto' : 'Área de preview' }}
          </span>
          <strong>{{ selectedFileName || 'Nenhum arquivo selecionado' }}</strong>
          <p>{{ hasSelectedFile ? previewCaption : uploadStateLabel }}</p>
        </div>
      </article>

      <section class="create-post__form card border-0">
        <form class="create-post__fields" novalidate @submit.prevent="handleSubmit">
          <div class="create-post__field">
            <label class="create-post__label" for="post-image">Imagem ou vídeo</label>
            <input
              id="post-image"
              ref="fileInput"
              class="form-control"
              type="file"
              accept="image/jpeg,image/png,image/webp,image/gif,video/mp4"
              @change="handleFileChange"
            />
            <span class="create-post__hint">{{ uploadStateLabel }}</span>
          </div>

          <div class="create-post__field">
            <label class="create-post__label" for="post-caption">
              Legenda
              <span class="create-post__char-count">
                {{ captionLength }}/{{ POST_CAPTION_MAX_LENGTH }}
              </span>
            </label>
            <textarea
              id="post-caption"
              v-model="form.caption"
              class="form-control create-post__textarea"
              :maxlength="POST_CAPTION_MAX_LENGTH"
              placeholder="Escreva algo sobre esse momento..."
              rows="4"
              @input="handleDraftInput"
            />
          </div>

          <div class="create-post__actions">
            <button
              class="btn btn-primary create-post__submit"
              type="submit"
              :disabled="!canPublish"
            >
              {{ publishButtonLabel }}
            </button>

            <button
              v-if="hasSelectedFile"
              class="btn btn-outline-secondary"
              type="button"
              @click="clearSelectedFile"
            >
              Limpar
            </button>

            <RouterLink
              v-if="publishedPost"
              class="btn btn-outline-secondary"
              :to="{ name: 'post-detalhes', params: { postId: publishedPost.id } }"
            >
              Ver post
            </RouterLink>

            <button
              v-if="publishedPost"
              class="btn btn-outline-secondary"
              type="button"
              @click="goToFeed"
            >
              Ir para o feed
            </button>
          </div>
        </form>
      </section>
    </section>
  </section>
</template>
