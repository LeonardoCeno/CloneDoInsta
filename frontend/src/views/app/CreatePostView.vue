<script setup>
import { computed, onBeforeUnmount, reactive, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { POST_CAPTION_MAX_LENGTH, useFeed } from '@/composables/useFeed'
import { extractErrorMessage } from '@/services/api'

const ACCEPTED_IMAGE_TYPES = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp']
const MAX_UPLOAD_BYTES = 5 * 1024 * 1024

const router = useRouter()

const form = reactive({
  caption: '',
})

const fileInput = ref(null)
const selectedFile = ref(null)
const imagePreviewUrl = ref('')
const selectedFileName = ref('')
const isSubmitting = ref(false)
const errorMessage = ref('')
const successMessage = ref('')
const publishedPost = ref(null)

const { createPost } = useFeed()

const trimmedCaption = computed(() => form.caption.trim())
const captionLength = computed(() => form.caption.length)
const hasSelectedImage = computed(() => Boolean(selectedFile.value))
const uploadStateLabel = computed(() => {
  if (hasSelectedImage.value) {
    return 'Imagem pronta para preview e publicação.'
  }
  return 'Aceita arquivos JPG, PNG ou WEBP com até 5 MB.'
})
const publishButtonLabel = computed(() => (isSubmitting.value ? 'Publicando...' : 'Publicar post'))
const canPublish = computed(
  () => Boolean(hasSelectedImage.value && trimmedCaption.value) && !isSubmitting.value,
)
const previewAlt = computed(() =>
  trimmedCaption.value
    ? `Preview do post com a legenda: ${trimmedCaption.value}`
    : 'Preview da imagem selecionada para o post',
)
const previewCaption = computed(
  () => trimmedCaption.value || 'Sua legenda aparece aqui assim que você começar a escrever.',
)

function revokePreview() {
  if (imagePreviewUrl.value && imagePreviewUrl.value.startsWith('blob:')) {
    URL.revokeObjectURL(imagePreviewUrl.value)
  }
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
  if (errorMessage.value) {
    errorMessage.value = ''
  }
}

function clearSelectedImage() {
  revokePreview()
  selectedFile.value = null
  imagePreviewUrl.value = ''
  selectedFileName.value = ''

  if (fileInput.value) {
    fileInput.value.value = ''
  }
}

function handleFileChange(event) {
  const file = event.target.files?.[0]

  if (!file) {
    return
  }

  clearFeedback()

  if (!ACCEPTED_IMAGE_TYPES.includes(file.type)) {
    clearSelectedImage()
    errorMessage.value = 'Use uma imagem nos formatos JPG, PNG ou WEBP.'
    return
  }

  if (file.size > MAX_UPLOAD_BYTES) {
    clearSelectedImage()
    errorMessage.value = 'Escolha uma imagem com até 5 MB.'
    return
  }

  revokePreview()
  selectedFile.value = file
  selectedFileName.value = file.name
  imagePreviewUrl.value = URL.createObjectURL(file)
}

async function handleSubmit() {
  clearFeedback()

  if (!selectedFile.value) {
    errorMessage.value = 'Selecione uma imagem antes de publicar.'
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
    clearSelectedImage()
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
          O fluxo abaixo recebe a imagem, gera preview em tempo real, envia tudo para a API e
          publica direto na sua timeline.
        </p>
      </div>

      <ul class="create-post__hero-facts">
        <li>Preview instantâneo da imagem selecionada</li>
        <li>Legenda com limite de {{ POST_CAPTION_MAX_LENGTH }} caracteres</li>
        <li>Feedback visual de sucesso ou erro após publicar</li>
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
        <div class="create-post__frame" :class="{ 'has-image': hasSelectedImage }">
          <img v-if="hasSelectedImage" :src="imagePreviewUrl" :alt="previewAlt" />

          <div v-else class="create-post__placeholder">
            <strong>Sua imagem aparece aqui</strong>
            <p>
              Escolha um arquivo para visualizar o enquadramento e preparar a legenda antes da
              publicação.
            </p>
          </div>
        </div>

        <div class="create-post__preview-copy">
          <span class="create-post__preview-label">
            {{ hasSelectedImage ? 'Preview pronta' : 'Área de preview' }}
          </span>
          <strong>{{ selectedFileName || 'Nenhum arquivo selecionado' }}</strong>
          <p>{{ hasSelectedImage ? previewCaption : uploadStateLabel }}</p>
        </div>
      </article>

      <section class="create-post__form card border-0">
        <form class="create-post__fields" novalidate @submit.prevent="handleSubmit">
          <div class="create-post__field">
            <label class="create-post__label" for="post-image">Imagem</label>
            <input
              id="post-image"
              ref="fileInput"
              class="form-control"
              type="file"
              accept="image/jpeg,image/png,image/webp"
              @change="handleFileChange"
            />
            <p class="create-post__hint">{{ uploadStateLabel }}</p>
          </div>

          <div v-if="hasSelectedImage" class="create-post__secondary-actions">
            <span>{{ selectedFileName }}</span>
            <button class="btn btn-outline-secondary btn-sm" type="button" @click="clearSelectedImage">
              Remover imagem
            </button>
          </div>

          <div class="create-post__field">
            <div class="create-post__label-row">
              <label class="create-post__label" for="post-caption">Legenda</label>
              <span>{{ captionLength }}/{{ POST_CAPTION_MAX_LENGTH }}</span>
            </div>
            <textarea
              id="post-caption"
              v-model="form.caption"
              class="form-control create-post__textarea"
              :maxlength="POST_CAPTION_MAX_LENGTH"
              rows="6"
              placeholder="Conte o momento, a ideia ou a história por trás dessa imagem."
              @input="handleDraftInput"
            />
          </div>

          <button class="btn btn-primary btn-lg create-post__submit" type="submit" :disabled="!canPublish">
            {{ publishButtonLabel }}
          </button>
        </form>
      </section>
    </section>

    <section v-if="publishedPost" class="create-post__published card border-0">
      <div>
        <span class="create-post__eyebrow">Pronto para circular</span>
        <h3>Seu novo post já conta no perfil e no feed.</h3>
        <p>
          A publicação foi salva com a autoria de @{{ publishedPost.author.username }} e já pode
          receber curtidas e comentários.
        </p>
      </div>

      <div class="create-post__published-actions">
        <RouterLink class="btn btn-outline-secondary" :to="{ name: 'feed' }" @click="goToFeed">
          Ver no feed
        </RouterLink>
      </div>
    </section>
  </section>
</template>

<style scoped>
.create-post {
  display: grid;
  gap: 1rem;
}

.create-post__hero,
.create-post__preview,
.create-post__form,
.create-post__published {
  padding: 1.4rem;
  border-radius: 1.75rem;
}

.create-post__hero {
  display: grid;
  gap: 1rem;
  background:
    linear-gradient(135deg, rgba(0, 149, 246, 0.12) 0%, rgba(0, 149, 246, 0.04) 100%),
    var(--app-surface);
}

.create-post__eyebrow,
.create-post__preview-label {
  display: inline-block;
  margin-bottom: 0.35rem;
  color: var(--app-accent-strong);
  font-size: 0.78rem;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.create-post__hero-copy h2,
.create-post__published h3 {
  margin: 0 0 0.35rem;
  font-size: clamp(1.65rem, 4vw, 2.3rem);
  font-weight: 800;
}

.create-post__hero-copy p,
.create-post__preview-copy p,
.create-post__published p,
.create-post__hint,
.create-post__label-row span,
.create-post__secondary-actions span,
.create-post__placeholder p {
  margin: 0;
  color: var(--app-muted);
  line-height: 1.65;
}

.create-post__hero-facts {
  display: grid;
  gap: 0.7rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.create-post__hero-facts li {
  padding: 0.95rem 1rem;
  border: 1px solid var(--app-border);
  border-radius: 1.15rem;
  font-weight: 700;
  background: var(--app-surface-soft);
}

.create-post__feedback {
  margin: 0;
  padding: 0.95rem 1rem;
  border-radius: 1rem;
  font-weight: 700;
}

.create-post__feedback.is-error {
  border: 1px solid rgba(255, 48, 64, 0.22);
  color: #ffb4ba;
  background: rgba(255, 48, 64, 0.08);
}

.create-post__feedback.is-success {
  border: 1px solid rgba(66, 211, 146, 0.2);
  color: #9ff0c7;
  background: rgba(66, 211, 146, 0.08);
}

.create-post__grid {
  display: grid;
  gap: 1rem;
}

.create-post__preview,
.create-post__form,
.create-post__published {
  background: var(--app-surface);
}

.create-post__preview {
  display: grid;
  gap: 1rem;
}

.create-post__frame {
  position: relative;
  display: grid;
  place-items: center;
  min-height: 19rem;
  overflow: hidden;
  border: 1px dashed var(--app-border-strong);
  border-radius: 1.5rem;
  background:
    linear-gradient(135deg, rgba(0, 149, 246, 0.12) 0%, rgba(0, 149, 246, 0.02) 100%),
    var(--app-surface-soft);
}

.create-post__frame.has-image {
  border-style: solid;
  background: var(--app-surface-soft);
}

.create-post__frame img {
  display: block;
  width: 100%;
  height: 100%;
  min-height: 19rem;
  object-fit: cover;
}

.create-post__placeholder {
  display: grid;
  gap: 0.45rem;
  width: min(26rem, 100%);
  padding: 1.4rem;
  text-align: center;
}

.create-post__placeholder strong,
.create-post__preview-copy strong,
.create-post__published h3 {
  color: var(--app-text);
}

.create-post__preview-copy {
  display: grid;
  gap: 0.35rem;
}

.create-post__fields {
  display: grid;
  gap: 1rem;
}

.create-post__field {
  display: grid;
  gap: 0.55rem;
}

.create-post__label,
.create-post__label-row span,
.create-post__secondary-actions span {
  font-size: 0.94rem;
}

.create-post__label {
  font-weight: 800;
}

.create-post__label-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
}

.create-post__hint {
  font-size: 0.92rem;
}

.create-post__secondary-actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 0.9rem 1rem;
  border-radius: 1rem;
  background: var(--app-surface-soft);
}

.create-post__textarea {
  min-height: 10rem;
  resize: vertical;
}

.create-post__submit:disabled {
  cursor: not-allowed;
  opacity: 0.6;
}

.create-post__published {
  display: grid;
  gap: 1rem;
  align-items: center;
}

.create-post__published-actions {
  display: flex;
  gap: 0.75rem;
}

@media (min-width: 768px) {
  .create-post__hero-facts {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }

  .create-post__published {
    grid-template-columns: minmax(0, 1fr) auto;
  }
}

@media (min-width: 992px) {
  .create-post__grid {
    grid-template-columns: minmax(0, 1.05fr) minmax(0, 0.95fr);
    align-items: start;
  }

  .create-post__preview {
    position: sticky;
    top: 1.5rem;
  }
}
</style>
