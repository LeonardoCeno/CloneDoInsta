import { createRouter, createWebHistory } from 'vue-router'
import { pinia } from '@/stores'
import { useAuthStore } from '@/stores/auth'

import AppLayout from '@/layouts/AppLayout.vue'
import AuthLayout from '@/layouts/AuthLayout.vue'

import FeedView from '@/views/app/FeedView.vue'
import CreatePostView from '@/views/app/CreatePostView.vue'
import DiscoverView from '@/views/app/DiscoverView.vue'
import PostDetailsView from '@/views/app/PostDetailsView.vue'
import ProfileView from '@/views/app/ProfileView.vue'
import EditProfileView from '@/views/app/EditProfileView.vue'
import ProfileConnectionsView from '@/views/app/ProfileConnectionsView.vue'
import SavedPostsView from '@/views/app/SavedPostsView.vue'
import ExploreView from '@/views/app/ExploreView.vue'
import NotificationsView from '@/views/app/NotificationsView.vue'

import LoginView from '@/views/auth/LoginView.vue'
import RegisterView from '@/views/auth/RegisterView.vue'

import NotFoundView from '@/views/NotFoundView.vue'

const routes = [
  {
    path: '/',
    component: AppLayout,
    meta: {
      requiresAuth: true,
    },
    children: [
      {
        path: '',
        redirect: { name: 'feed' },
      },
      {
        path: 'feed',
        name: 'feed',
        component: FeedView,
        meta: {
          navItem: 'feed',
          sectionTitle: 'Home',
          sectionDescription: 'Acompanhe as publicações da sua rede e mantenha o feed como ponto de partida.',
          footerLabel: 'Espaço ideal para receber posts, paginação e interações inline.',
        },
      },
      {
        path: 'posts/:postId',
        name: 'post-detalhes',
        component: PostDetailsView,
        meta: {
          navItem: 'feed',
          sectionTitle: 'Detalhes do Post',
          sectionDescription:
            'Abra um post individual para acompanhar curtidas, comentários e o contexto completo da publicação.',
          footerLabel:
            'A tela detalhada amplia a interação com paginação de comentários e gestão do próprio post.',
        },
      },
      {
        path: 'criar',
        name: 'criar',
        component: CreatePostView,
        meta: {
          navItem: 'criar',
          sectionTitle: 'Criar Post',
          sectionDescription: 'Prepare uploads, legendas e feedbacks visuais dentro de um fluxo simples.',
          footerLabel: 'Área pronta para preview de imagem, formulário e estados de publicação.',
        },
      },
      {
        path: 'descobrir',
        name: 'descobrir',
        component: DiscoverView,
        meta: {
          navItem: 'descobrir',
          sectionTitle: 'Descobrir Pessoas',
          sectionDescription: 'Explore todos os perfis da rede e comece a seguir quem você ainda não conhece.',
          footerLabel: 'Lista completa de usuários com ação de seguir direto do card.',
        },
      },
      {
        path: 'perfil',
        name: 'perfil',
        component: ProfileView,
        meta: {
          navItem: 'perfil',
          sectionTitle: 'Perfil',
          sectionDescription: 'Acompanhe bio, grade de posts, relacionamento entre contas e atalhos para editar o próprio perfil.',
          footerLabel: 'Perfil, listas de conexões e edição agora compartilham o mesmo fluxo.',
        },
      },
      {
        path: 'perfil/editar',
        name: 'perfil-editar',
        component: EditProfileView,
        meta: {
          navItem: 'perfil',
          sectionTitle: 'Editar Perfil',
          sectionDescription: 'Atualize foto, nome e bio com persistência imediata na API.',
          footerLabel: 'Alterações feitas aqui refletem no topo da conta, no perfil e nos seus posts.',
        },
      },
      {
        path: 'perfil/lista/:type',
        name: 'perfil-lista',
        component: ProfileConnectionsView,
        meta: {
          navItem: 'perfil',
          sectionTitle: 'Conexões',
          sectionDescription: 'Navegue pelas listas de seguidores e de perfis seguidos sem sair do fluxo principal.',
          footerLabel: 'As relações ficam persistidas no backend e afetam o feed e o perfil em tempo real.',
        },
      },
      {
        path: 'notificacoes',
        name: 'notificacoes',
        component: NotificationsView,
        meta: { navItem: 'notificacoes', sectionTitle: 'Notificações' },
      },
      {
        path: 'explorar',
        name: 'explorar',
        component: ExploreView,
        meta: {
          navItem: 'explorar',
          sectionTitle: 'Explorar',
          sectionDescription: 'Descubra posts de todos os perfis da plataforma.',
          footerLabel: 'Grid de posts aleatórios de toda a rede.',
        },
      },
      {
        path: 'salvos',
        name: 'salvos',
        component: SavedPostsView,
        meta: {
          navItem: 'salvos',
          sectionTitle: 'Posts Salvos',
          sectionDescription: 'Acesse todas as publicações que você salvou para ver depois.',
          footerLabel: 'Posts salvos ficam disponíveis aqui independente de quem você segue.',
        },
      },
    ],
  },
  {
    path: '/',
    component: AuthLayout,
    meta: {
      requiresGuest: true,
    },
    children: [
      {
        path: 'login',
        name: 'login',
        component: LoginView,
      },
      {
        path: 'cadastro',
        name: 'cadastro',
        component: RegisterView,
      },
    ],
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'not-found',
    component: NotFoundView,
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior() {
    return { top: 0 }
  },
})

const authStore = useAuthStore(pinia)

router.beforeEach(async (to) => {
  if (!authStore.hydrated) {
    await authStore.hydrateAuthState()
  }

  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    return {
      name: 'login',
      query: {
        redirect: to.fullPath,
      },
    }
  }

  if (to.meta.requiresGuest && authStore.isAuthenticated) {
    return { name: 'feed' }
  }

  return true
})

export default router
