<script setup>
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '@/composables/useAuth'
import { extractErrorMessage } from '@/services/api'

const router = useRouter()
const { register } = useAuth()

const form = reactive({
  name: '',
  username: '',
  email: '',
  password: '',
  confirmPassword: '',
})

const isSubmitting = ref(false)
const errorMessage = ref('')

function validateForm() {
  if (!form.name || !form.username || !form.email || !form.password || !form.confirmPassword) {
    return 'Preencha todos os campos para criar a conta.'
  }

  if (!/^[a-zA-Z0-9._]+$/.test(form.username)) {
    return 'Username deve conter apenas letras, números, ponto e sublinhado.'
  }

  if (form.username.length < 3) {
    return 'Username deve ter pelo menos 3 caracteres.'
  }

  if (form.password.length < 8) {
    return 'Senha deve ter no mínimo 8 caracteres.'
  }

  if (form.password !== form.confirmPassword) {
    return 'As senhas informadas são diferentes.'
  }

  return ''
}

async function handleSubmit() {
  errorMessage.value = ''

  const validationError = validateForm()

  if (validationError) {
    errorMessage.value = validationError
    return
  }

  isSubmitting.value = true

  try {
    await register({
      name: form.name,
      username: form.username,
      email: form.email,
      password: form.password,
      password_confirmation: form.confirmPassword,
    })
    await router.replace('/feed')
  } catch (error) {
    errorMessage.value = extractErrorMessage(error, 'Não foi possível criar sua conta agora.')
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <section>
    <h2 class="h5 fw-semibold mb-2">Cadastro</h2>
    <p class="text-body-secondary mb-4">Crie sua conta para começar a publicar e seguir perfis.</p>

    <form class="row g-3" novalidate @submit.prevent="handleSubmit">
      <div class="col-12">
        <label class="form-label" for="nome">Nome</label>
        <input
          id="nome"
          v-model.trim="form.name"
          class="form-control"
          :class="{ 'is-invalid': Boolean(errorMessage) }"
          type="text"
          autocomplete="name"
          placeholder="Seu nome completo"
          :aria-invalid="Boolean(errorMessage)"
          required
        />
      </div>

      <div class="col-12">
        <label class="form-label" for="username">Username</label>
        <input
          id="username"
          v-model.trim="form.username"
          class="form-control"
          :class="{ 'is-invalid': Boolean(errorMessage) }"
          type="text"
          autocomplete="username"
          placeholder="seu_usuario"
          :aria-invalid="Boolean(errorMessage)"
          required
        />
      </div>

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
          autocomplete="new-password"
          placeholder="Crie sua senha (mín. 8 caracteres)"
          :aria-invalid="Boolean(errorMessage)"
          required
        />
      </div>

      <div class="col-12">
        <label class="form-label" for="confirmar-senha">Confirmar senha</label>
        <input
          id="confirmar-senha"
          v-model="form.confirmPassword"
          class="form-control"
          :class="{ 'is-invalid': Boolean(errorMessage) }"
          type="password"
          autocomplete="new-password"
          placeholder="Repita sua senha"
          :aria-invalid="Boolean(errorMessage)"
          required
        />
      </div>

      <div v-if="errorMessage" class="col-12">
        <div class="alert alert-danger py-2 mb-0" role="alert">{{ errorMessage }}</div>
      </div>

      <div class="col-12">
        <button class="btn btn-primary w-100" type="submit" :disabled="isSubmitting">
          {{ isSubmitting ? 'Criando conta...' : 'Criar conta' }}
        </button>
      </div>
    </form>

    <p class="text-body-secondary small mb-0 mt-4">
      Já tem conta?
      <RouterLink class="link-primary text-decoration-none fw-semibold" to="/login">
        Entrar
      </RouterLink>
    </p>
  </section>
</template>
