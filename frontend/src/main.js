import { createApp } from 'vue'
import App from './App.vue'
import router from './router'
import { pinia } from './stores'
import { useAuthStore } from './stores/auth'
import { configureApi } from './services/api'
import 'bootstrap/dist/css/bootstrap.min.css'
import './assets/styles/theme.css'

const app = createApp(App).use(pinia)

const authStore = useAuthStore(pinia)

configureApi({
  getToken: () => authStore.token,
  onUnauthorized: () => {
    authStore.clearSession()
  },
})

app.use(router).mount('#app')
