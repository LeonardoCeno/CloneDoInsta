# InstaClone — Frontend (Vue 3)

## Visão Geral

O InstaClone é uma rede social inspirada no Instagram, construída como projeto final da disciplina. Este repositório contém o **frontend** da aplicação: uma SPA em Vue 3 que consome uma API RESTful externa (o backend do projeto vive em `../backend`).

A aplicação está integrada à API: autenticação por JWT, feed paginado por cursor, upload multipart de imagens e gerenciamento de seguidores/curtidas/comentários vêm todos do servidor. O único estado persistido localmente é o token de acesso, guardado em `localStorage` sob a chave `instaclone.token`.

## Stack

- **Vue 3** (`^3.5`) com `<script setup>`
- **Vite 8** como bundler/dev server
- **Vue Router 4** com histórico HTML5 e guards globais
- **Pinia 3** para o estado compartilhado
- **Axios** como cliente HTTP, com interceptors de `Authorization` e `401`
- **Bootstrap 5** (reset/utilidades) + tema CSS próprio em [src/assets/styles/theme.css](src/assets/styles/theme.css)
- **Node.js** `^20.19.0 || >=22.12.0`

## Como rodar

```bash
# instalar dependências
npm install

# subir em modo desenvolvimento (http://localhost:5173)
npm run dev

# build de produção para ./dist
npm run build

# pré-visualizar o build
npm run preview
```

### Variáveis de ambiente

Copie [.env.example](.env.example) para `.env` e ajuste a URL da API se necessário:

```
VITE_API_URL=http://localhost:8000/api
```

Quando a variável não é definida, o cliente HTTP em [src/services/api.js](src/services/api.js) usa `http://localhost:8000/api` como fallback.

### Docker

O projeto tem um build multi-stage ([Dockerfile](Dockerfile)) que gera os assets com Node e serve o `dist/` via Nginx. Para subir com Docker Compose:

```bash
docker compose up --build
```

O serviço fica exposto em `http://localhost:3000` ([compose.yaml](compose.yaml)). Passe `VITE_API_URL` para o build quando a API não estiver em `localhost:8000`.

## Estrutura do código

```
src/
  assets/styles/      tema e variáveis CSS globais
  components/
    feed/             PostCard
    layout/           AppShell, AppIcon
    profile/          ProfileAvatar
  composables/        useAuth, useFeed (wrappers sobre os stores)
  layouts/            AppLayout (área autenticada), AuthLayout (login/cadastro)
  router/             rotas + guards de autenticação
  services/           clientes HTTP por domínio
  stores/             Pinia: auth, feed
  views/
    app/              Feed, CreatePost, Discover, PostDetails, Profile,
                      EditProfile, ProfileConnections
    auth/             Login, Register
    NotFoundView
  App.vue             raiz (apenas <RouterView/>)
  main.js             bootstrap: Pinia, Router, configuração do axios
```

## Camada de serviços

Todo acesso à API é centralizado em [src/services/](src/services/). O módulo [api.js](src/services/api.js) cria a instância Axios, injeta o token JWT no header `Authorization: Bearer …` e dispara `clearSession()` no `auth` store quando a API responde com `401`. Também expõe o helper `extractErrorMessage` para traduzir respostas de erro da API em mensagens amigáveis.

Serviços disponíveis:

| Arquivo | Endpoints |
| --- | --- |
| [auth.service.js](src/services/auth.service.js) | `POST /auth/login`, `POST /auth/register`, `POST /auth/logout`, `POST /auth/refresh`, `GET /auth/me` |
| [users.service.js](src/services/users.service.js) | `GET /users/:username`, `PUT /users/me`, `POST /users/me/avatar`, `GET /users/search`, `GET /users/suggestions`, `GET /users/:id/posts` |
| [posts.service.js](src/services/posts.service.js) | `POST /posts`, `GET /posts/:id`, `PUT /posts/:id`, `DELETE /posts/:id` |
| [feed.service.js](src/services/feed.service.js) | `GET /feed` (paginação por cursor) |
| [likes.service.js](src/services/likes.service.js) | `POST /posts/:id/like`, `DELETE /posts/:id/unlike`, `GET /posts/:id/likes` |
| [comments.service.js](src/services/comments.service.js) | `GET/POST /posts/:id/comments`, `PUT/DELETE /comments/:id` |
| [follows.service.js](src/services/follows.service.js) | `POST /users/:id/follow`, `DELETE /users/:id/unfollow`, `GET /users/:id/followers`, `GET /users/:id/following`, `GET /users/:id/is-following` |
| [notifications.service.js](src/services/notifications.service.js) | `GET /notifications`, `GET /notifications/unread-count`, `PUT /notifications/read` |

## Gerenciamento de estado

O estado global fica em dois stores Pinia:

- [stores/auth.js](src/stores/auth.js) — token, usuário autenticado, hidratação inicial (revalida o token via `GET /auth/me`), login/cadastro/logout e atualização do perfil.
- [stores/feed.js](src/stores/feed.js) — lista de posts do feed com paginação por cursor, criação/remoção de posts, curtidas e contagem de comentários.

Os composables [useAuth](src/composables/useAuth.js) e [useFeed](src/composables/useFeed.js) expõem refs reativas (via `storeToRefs`) e as ações dos stores às views. Estados transitórios (campos de formulário, previews, mensagens momentâneas) continuam locais em cada view.

## Roteamento e autenticação

As rotas estão definidas em [router/index.js](src/router/index.js), divididas em dois layouts:

- **`AppLayout`** (`meta.requiresAuth`): `/feed`, `/criar`, `/descobrir`, `/posts/:postId`, `/perfil`, `/perfil/editar`, `/perfil/lista/:type`.
- **`AuthLayout`** (`meta.requiresGuest`): `/login`, `/cadastro`.

O `beforeEach` global hidrata a sessão na primeira navegação, redireciona para `/login` quando a rota exige autenticação e devolve o usuário logado ao feed caso tente acessar telas de convidado. Rotas desconhecidas caem em [NotFoundView](src/views/NotFoundView.vue).

## Telas principais

- **Feed** — lista posts da rede do usuário com paginação por cursor e botão "Mostrar mais posts". Curtidas e envio de comentários atualizam o store imediatamente.
- **Criar Post** — tela de upload com preview da imagem, campo de legenda (limite de `POST_CAPTION_MAX_LENGTH = 2200`) e botão de publicar. A imagem é validada no cliente (JPG/PNG/WEBP, até 5 MB) e enviada via `multipart/form-data`; assim que a API confirma a criação, a publicação entra no topo do feed.
- **Descobrir** — lista sugestões de contas (`GET /users/suggestions`) com ação de seguir direto do card.
- **Detalhes do Post** — a tela individual de post exibe imagem, legenda, autor, data, contagem de curtidas e comentários em um layout dedicado. Os comentários aparecem paginados com botão de carregar mais, seguem aceitando novos envios na própria tela e, quando o post pertence ao usuário autenticado, a interface também libera a ação de deletar a publicação.
- **Perfil** — avatar, bio, contadores (posts/seguidores/seguindo), grade de posts do usuário, botão de seguir/deixar de seguir e atalho para editar o próprio perfil.
- **Editar Perfil** — atualiza nome, username, bio (`PUT /users/me`) e foto (`POST /users/me/avatar`).
- **Conexões** (`/perfil/lista/:type`) — listagens paginadas de seguidores e seguidos.
- **Login / Cadastro** — formulários que delegam para o `auth` store e já deixam a sessão ativa ao concluir.

## Layout base

O [AppLayout](src/layouts/AppLayout.vue) monta uma sidebar de navegação (Home, Buscar, Criar, Perfil) e, quando a rota ativa é o feed, uma coluna lateral com a identidade do usuário e sugestões de contas para seguir. A área central troca de conteúdo via `<RouterView>` e adapta o modo de exibição (`feed`, `profile`, `default`) com base no `meta.navItem` da rota.
