<script setup>
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import ProfileAvatar from '@/components/profile/ProfileAvatar.vue'
import {
  PROFILE_BIO_MAX_LENGTH,
  PROFILE_NAME_MAX_LENGTH,
  PROFILE_USERNAME_MAX_LENGTH,
  useAuth,
} from '@/composables/useAuth'
import { extractErrorMessage } from '@/services/api'

const ACCEPTED_IMAGE_TYPES = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp']
const MAX_AVATAR_UPLOAD_BYTES = 2 * 1024 * 1024

const form = reactive({
  name: '',
  username: '',
  bio: '',
})

const fileInput = ref(null)
const selectedAvatarFile = ref(null)
const avatarPreviewUrl = ref('')
const selectedFileName = ref('')
const isSubmitting = ref(false)
const errorMessage = ref('')
const successMessage = ref('')
const hasLoadedAccount = ref(false)

const { currentUser, updateProfile, uploadAvatar } = useAuth()

const trimmedName = computed(() => form.name.trim())
const trimmedUsername = computed(() => form.username.trim().toLowerCase())
const trimmedBio = computed(() => form.bio.trim())
const bioLength = computed(() => form.bio.length)
const displayAvatar = computed(
  () => avatarPreviewUrl.value || currentUser.value?.avatarUrl || '',
)

const hasPendingFieldChanges = computed(() => {
  if (!currentUser.value) {
    return false
  }
  return (
    trimmedName.value !== (currentUser.value.name ?? '') ||
    trimmedUsername.value !== (currentUser.value.username ?? '') ||
    trimmedBio.value !== (currentUser.value.bio ?? '')
  )
})

const hasPendingAvatar = computed(() => Boolean(selectedAvatarFile.value))

const canSubmit = computed(
  () =>
    Boolean(trimmedName.value) &&
    Boolean(trimmedUsername.value) &&
    !isSubmitting.value &&
    (hasPendingFieldChanges.value || hasPendingAvatar.value),
)

const statusLabel = computed(() => {
  if (hasPendingAvatar.value) {
    return 'Foto selecionada. Ela será enviada ao salvar o perfil.'
  }
  return 'Você também pode manter o avatar atual.'
})

watch(
  currentUser,
  (user) => {
    if (!user || hasLoadedAccount.value) {
      return
    }
    form.name = user.name ?? ''
    form.username = user.username ?? ''
    form.bio = user.bio ?? ''
    hasLoadedAccount.value = true
  },
  { immediate: true },
)

function revokePreview() {
  if (avatarPreviewUrl.value && avatarPreviewUrl.value.startsWith('blob:')) {
    URL.revokeObjectURL(avatarPreviewUrl.value)
  }
}

function clearFeedback() {
  errorMessage.value = ''
  successMessage.value = ''
}

function handleFieldInput() {
  if (errorMessage.value || successMessage.value) {
    clearFeedback()
  }
}

function resetFileInput() {
  selectedFileName.value = ''
  if (fileInput.value) {
    fileInput.value.value = ''
  }
}

function clearSelectedAvatar() {
  revokePreview()
  avatarPreviewUrl.value = ''
  selectedAvatarFile.value = null
  resetFileInput()
}

function handleFileChange(event) {
  const file = event.target.files?.[0]
  if (!file) {
    return
  }

  clearFeedback()

  if (!ACCEPTED_IMAGE_TYPES.includes(file.type)) {
    resetFileInput()
    errorMessage.value = 'Use uma foto nos formatos JPG, PNG ou WEBP.'
    return
  }

  if (file.size > MAX_AVATAR_UPLOAD_BYTES) {
    resetFileInput()
    errorMessage.value = 'Escolha uma foto com até 2 MB.'
    return
  }

  revokePreview()
  selectedAvatarFile.value = file
  selectedFileName.value = file.name
  avatarPreviewUrl.value = URL.createObjectURL(file)
}

async function handleSubmit() {
  clearFeedback()

  if (!trimmedName.value) {
    errorMessage.value = 'Informe um nome para salvar o perfil.'
    return
  }

  if (!trimmedUsername.value) {
    errorMessage.value = 'Informe um username para salvar o perfil.'
    return
  }

  if (!/^[a-zA-Z0-9._]+$/.test(trimmedUsername.value)) {
    errorMessage.value = 'Username deve conter apenas letras, números, ponto e sublinhado.'
    return
  }

  isSubmitting.value = true

  try {
    if (hasPendingFieldChanges.value) {
      await updateProfile({
        name: trimmedName.value,
        username: trimmedUsername.value,
        bio: trimmedBio.value,
      })
    }

    if (hasPendingAvatar.value) {
      await uploadAvatar(selectedAvatarFile.value)
      clearSelectedAvatar()
    }

    successMessage.value = 'Perfil atualizado com sucesso.'
  } catch (error) {
    errorMessage.value = extractErrorMessage(error, 'Não foi possível salvar o perfil agora.')
  } finally {
    isSubmitting.value = false
  }
}

onBeforeUnmount(() => {
  revokePreview()
})
</script>

<template>
  <section v-if="currentUser" class="edit-profile">
    <section class="edit-profile__hero card border-0">
      <div>
        <span class="edit-profile__eyebrow">Edição de perfil</span>
        <h2>Atualize como sua conta aparece no app.</h2>
        <p>
          Nome, username, bio e foto ficam salvos no backend e refletem no perfil, no topo da
          aplicação e nos posts publicados por você.
        </p>
      </div>

      <RouterLink class="btn btn-outline-secondary" :to="{ name: 'perfil' }">
        Voltar ao perfil
      </RouterLink>
    </section>

    <p v-if="errorMessage" class="edit-profile__feedback is-error" role="alert">
      {{ errorMessage }}
    </p>

    <p v-if="successMessage" class="edit-profile__feedback is-success" role="status">
      {{ successMessage }}
    </p>

    <section class="edit-profile__grid">
      <article class="edit-profile__preview card border-0">
        <div class="edit-profile__preview-avatar">
          <ProfileAvatar
            :name="trimmedName || currentUser.name"
            :username="trimmedUsername || currentUser.username"
            :avatar-url="displayAvatar"
            :colors="currentUser.colors"
            size="xl"
          />
        </div>

        <div class="edit-profile__preview-copy">
          <span class="edit-profile__preview-label">Preview ao vivo</span>
          <strong>{{ trimmedName || currentUser.name }}</strong>
          <span>@{{ trimmedUsername || currentUser.username }}</span>
          <p>{{ trimmedBio || 'Sua bio aparece aqui assim que você começar a escrever.' }}</p>
        </div>

        <div class="edit-profile__preview-state">
          <strong>{{ selectedFileName || 'Nenhuma foto nova selecionada' }}</strong>
          <span>{{ statusLabel }}</span>
        </div>
      </article>

      <section class="edit-profile__form card border-0">
        <form class="edit-profile__fields" novalidate @submit.prevent="handleSubmit">
          <div class="edit-profile__field">
            <label class="edit-profile__label" for="profile-name">Nome</label>
            <input
              id="profile-name"
              v-model="form.name"
              class="form-control"
              type="text"
              :maxlength="PROFILE_NAME_MAX_LENGTH"
              placeholder="Seu nome no perfil"
              @input="handleFieldInput"
            />
          </div>

          <div class="edit-profile__field">
            <label class="edit-profile__label" for="profile-username">Username</label>
            <input
              id="profile-username"
              v-model.trim="form.username"
              class="form-control"
              type="text"
              :maxlength="PROFILE_USERNAME_MAX_LENGTH"
              placeholder="seu_usuario"
              @input="handleFieldInput"
            />
            <p class="edit-profile__hint">Apenas letras, números, ponto e sublinhado.</p>
          </div>

          <div class="edit-profile__field">
            <div class="edit-profile__field-head">
              <label class="edit-profile__label" for="profile-bio">Bio</label>
              <span>{{ bioLength }}/{{ PROFILE_BIO_MAX_LENGTH }}</span>
            </div>
            <textarea
              id="profile-bio"
              v-model="form.bio"
              class="form-control"
              rows="5"
              :maxlength="PROFILE_BIO_MAX_LENGTH"
              placeholder="Conte um pouco sobre você"
              @input="handleFieldInput"
            />
          </div>

          <div class="edit-profile__field">
            <label class="edit-profile__label" for="profile-photo">Foto</label>
            <input
              id="profile-photo"
              ref="fileInput"
              class="form-control"
              type="file"
              accept="image/jpeg,image/png,image/webp"
              @change="handleFileChange"
            />
            <p class="edit-profile__hint">
              Aceita JPG, PNG ou WEBP com até 2 MB.
            </p>
          </div>

          <div class="edit-profile__actions">
            <button class="btn btn-primary" type="submit" :disabled="!canSubmit">
              {{ isSubmitting ? 'Salvando...' : 'Salvar perfil' }}
            </button>
            <button
              class="btn btn-outline-secondary"
              type="button"
              :disabled="!selectedFileName"
              @click="clearSelectedAvatar"
            >
              Remover seleção de foto
            </button>
          </div>
        </form>
      </section>
    </section>
  </section>

  <section v-else class="card border-0 shadow-sm">
    <div class="card-body p-4">
      <h2 class="h4 mb-3">Editar perfil</h2>
      <p class="text-body-secondary mb-3">
        Não encontrei uma conta ativa para editar agora. Volte ao perfil e tente novamente.
      </p>
      <RouterLink class="btn btn-outline-secondary" :to="{ name: 'perfil' }">
        Voltar ao perfil
      </RouterLink>
    </div>
  </section>
</template>

<style scoped>
.edit-profile {
  display: grid;
  gap: 1rem;
}

.edit-profile__hero,
.edit-profile__preview,
.edit-profile__form {
  padding: 1.4rem;
  border-radius: 1.75rem;
  background: var(--app-surface);
}

.edit-profile__hero {
  display: grid;
  gap: 1rem;
}

.edit-profile__eyebrow,
.edit-profile__preview-label {
  display: inline-block;
  margin-bottom: 0.35rem;
  color: var(--app-accent-strong);
  font-size: 0.78rem;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.edit-profile__hero h2 {
  margin: 0 0 0.4rem;
  font-size: clamp(1.6rem, 4vw, 2.35rem);
  font-weight: 800;
}

.edit-profile__hero p,
.edit-profile__preview-copy span,
.edit-profile__preview-copy p,
.edit-profile__preview-state span,
.edit-profile__hint,
.edit-profile__field-head span {
  margin: 0;
  color: var(--app-muted);
  line-height: 1.65;
}

.edit-profile__feedback {
  margin: 0;
  padding: 0.95rem 1rem;
  border-radius: 1rem;
  font-weight: 700;
}

.edit-profile__feedback.is-error {
  color: #ffb4ba;
  background: rgba(255, 48, 64, 0.08);
}

.edit-profile__feedback.is-success {
  color: #9ff0c7;
  background: rgba(66, 211, 146, 0.08);
}

.edit-profile__grid {
  display: grid;
  gap: 1rem;
}

.edit-profile__preview {
  display: grid;
  gap: 1rem;
  align-content: start;
}

.edit-profile__preview-avatar {
  display: flex;
  justify-content: center;
}

.edit-profile__preview-copy {
  display: grid;
  gap: 0.15rem;
  text-align: center;
}

.edit-profile__preview-copy strong {
  font-size: 1.3rem;
}

.edit-profile__preview-state {
  display: grid;
  gap: 0.2rem;
  padding: 1rem;
  border-radius: 1.2rem;
  background: var(--app-surface-soft);
}

.edit-profile__form {
  align-content: start;
}

.edit-profile__fields {
  display: grid;
  gap: 1rem;
}

.edit-profile__field {
  display: grid;
  gap: 0.45rem;
}

.edit-profile__field-head {
  display: flex;
  justify-content: space-between;
  gap: 0.75rem;
}

.edit-profile__label {
  font-weight: 800;
}

.edit-profile__hint {
  font-size: 0.92rem;
}

.edit-profile__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
}

@media (min-width: 992px) {
  .edit-profile__hero {
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: center;
  }

  .edit-profile__grid {
    grid-template-columns: minmax(280px, 340px) minmax(0, 1fr);
  }
}
</style>
