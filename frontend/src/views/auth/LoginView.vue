<script setup>
import { computed, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuth } from '@/composables/useAuth'
import { extractErrorMessage } from '@/services/api'

const route = useRoute()
const router = useRouter()
const { login } = useAuth()

const form = reactive({
  email: '',
  password: '',
})

const isSubmitting = ref(false)
const errorMessage = ref('')

const redirectPath = computed(() => {
  const value = route.query.redirect
  return typeof value === 'string' && value.startsWith('/') ? value : '/feed'
})

const helperMessage = computed(() => {
  return redirectPath.value !== '/feed'
    ? 'Faça login para acessar a página solicitada.'
    : ''
})

async function handleSubmit() {
  errorMessage.value = ''

  if (!form.email || !form.password) {
    errorMessage.value = 'Informe e-mail e senha para continuar.'
    return
  }

  isSubmitting.value = true

  try {
    await login({
      email: form.email,
      password: form.password,
    })
    await router.replace(redirectPath.value)
  } catch (error) {
    errorMessage.value = extractErrorMessage(error, 'Não foi possível realizar o login.')
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <section>
    <h2 class="h5 fw-semibold mb-2">Login</h2>
    <p class="text-body-secondary mb-4">
      Acesse sua conta com e-mail e senha cadastrados.
    </p>

    <form class="row g-3" novalidate @submit.prevent="handleSubmit">
      <div class="col-12">
        <label class="form-label" for="email">E-mail</label>
        <input
          id="email"
          v-model.trim="form.email"
          class="form-control"
          :class="{ 'is-invalid': Boolean(errorMessage) }"
          type="email"
          autocomplete="email"
          placeholder="seuemail@dominio.com"
          :aria-invalid="Boolean(errorMessage)"
          required
        />
      </div>

      <div class="col-12">
        <label class="form-label" for="senha">Senha</label>
        <input
          id="senha"
          v-model="form.password"
          class="form-control"
          :class="{ 'is-invalid': Boolean(errorMessage) }"
          type="password"
          autocomplete="current-password"
          placeholder="Digite sua senha"
          :aria-invalid="Boolean(errorMessage)"
          required
        />
      </div>

      <div v-if="errorMessage" class="col-12">
        <div class="alert alert-danger py-2 mb-0" role="alert">{{ errorMessage }}</div>
      </div>

      <div v-if="helperMessage && !errorMessage" class="col-12">
        <p class="form-text mb-0">{{ helperMessage }}</p>
      </div>

      <div class="col-12">
        <button class="btn btn-primary w-100" type="submit" :disabled="isSubmitting">
          {{ isSubmitting ? 'Entrando...' : 'Entrar' }}
        </button>
      </div>
    </form>

    <p class="text-body-secondary small mb-0 mt-4">
      Não tem conta?
      <RouterLink class="link-primary text-decoration-none fw-semibold" to="/cadastro">
        Criar agora
      </RouterLink>
    </p>
  </section>
</template>
