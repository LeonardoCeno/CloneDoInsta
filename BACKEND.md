# Backend — Documentação Completa

> Laravel 11 + FrankenPHP + MySQL  
> API REST autenticada via Laravel Sanctum (tokens Bearer)

---

## Visão Geral

O backend é uma API REST que alimenta a rede social Manya. Toda comunicação com o frontend é feita via JSON. A autenticação usa tokens Bearer gerados pelo Sanctum. Não há sessões — cada requisição autenticada envia o token no header `Authorization: Bearer {token}`.

---

## Stack Técnica

| Componente | Tecnologia |
|---|---|
| Linguagem | PHP 8.4 |
| Framework | Laravel 11 |
| Servidor | FrankenPHP (baseado em Caddy) |
| Banco de Dados | MySQL 8.0 |
| Autenticação | Laravel Sanctum (tokens Bearer) |
| Armazenamento | Sistema de arquivos local (storage/) |
| Containerização | Docker multistage |
| Deploy | Google Cloud Run |

---

## Estrutura de Pastas

```
backend/
├── app/
│   ├── Exceptions/          # Exceções personalizadas
│   ├── Http/
│   │   ├── Controllers/     # Recebem a requisição e retornam resposta
│   │   ├── Requests/        # Validação de dados de entrada
│   │   └── Resources/       # Formatação de dados de saída (JSON)
│   ├── Models/              # Representam tabelas do banco de dados
│   ├── Observers/           # Reagem a eventos dos models (criação, deleção...)
│   ├── Policies/            # Regras de autorização (quem pode fazer o quê)
│   ├── Providers/           # Configuração e registro de serviços
│   └── Services/            # Lógica de negócio isolada
├── bootstrap/
│   └── app.php              # Ponto de entrada e configuração da aplicação
├── config/                  # Arquivos de configuração
├── database/
│   ├── migrations/          # Define e evolui o schema do banco
│   ├── factories/           # Gera dados falsos para testes
│   └── seeders/             # Popula o banco com dados iniciais
├── docker/
│   ├── backend/
│   │   ├── entrypoint.sh    # Script executado ao iniciar o container
│   │   └── php.ini          # Configurações do PHP
│   └── frontend/
│       └── nginx.conf       # Configuração do servidor do frontend
├── routes/
│   └── api.php              # Define todas as rotas da API
├── storage/                 # Arquivos enviados pelos usuários
└── public/                  # Arquivos acessíveis publicamente (storage symlink)
```

---

## Fluxo de uma Requisição

```
Frontend
  │
  ▼
HTTP Request (Authorization: Bearer {token})
  │
  ▼
FrankenPHP (servidor web)
  │
  ▼
bootstrap/app.php (configura middleware, rotas, exceções)
  │
  ▼
routes/api.php (encontra a rota correspondente)
  │
  ▼
Middleware (autenticação, CORS, rate limiting)
  │
  ▼
Request (valida os dados de entrada)
  │
  ▼
Controller (coordena a operação)
  │
  ▼
Service (executa a lógica de negócio)
  │
  ▼
Model / Eloquent (acessa o banco de dados)
  │
  ▼
Resource (formata o JSON de resposta)
  │
  ▼
HTTP Response → Frontend
```

---

## Banco de Dados — Migrations

As migrations definem o schema do banco em ordem cronológica. O Laravel executa cada uma exatamente uma vez.

| Ordem | Arquivo | O que cria |
|---|---|---|
| 1 | `000000_create_users_table` | Tabela `users` (auth base), `password_reset_tokens`, `sessions` |
| 2 | `000001_create_cache_table` | Tabela `cache` para cache do Laravel |
| 3 | `000002_create_jobs_table` | Tabela `jobs` para filas |
| 4 | `create_follows_table` | Tabela `follows` — relacionamentos seguidor/seguido com status |
| 5 | `create_posts_table` | Tabela `posts` — conteúdo publicado |
| 6 | `create_likes_table` | Tabela `likes` — curtidas (chave composta user+post) |
| 7 | `create_comments_table` | Tabela `comments` — comentários em posts |
| 8 | `create_notifications_table` | Tabela `notifications` — notificações de atividade |
| 9 | `create_personal_access_tokens_table` | Tabela do Sanctum para tokens de API |
| 10 | `expand_user_name_and_bio` | Aumenta limite de name (255) e bio (500) |
| 11 | `create_saves_table` | Tabela `saves` — posts salvos (chave composta user+post) |
| 12 | `create_stories_table` | Tabela `stories` — conteúdo que expira em 24h |
| 13 | `create_story_views_table` | Tabela `story_views` — controle de quem viu cada story |
| 14 | `create_reposts_table` | Tabela `reposts` — republicações (chave composta user+post) |
| 15 | `add_is_private_to_users_table` | Coluna `is_private` em users |
| 16 | `add_status_to_follows_table` | Coluna `status` em follows (pending/accepted) |

**Chaves compostas**: `likes`, `saves`, `reposts` e `story_views` usam (user_id + post_id) como chave primária, impedindo duplicatas diretamente no banco.

---

## Models (app/Models/)

Os models representam tabelas do banco e encapsulam os dados e relacionamentos.

### User.php
Representa um usuário da plataforma.

- **Campos**: `id`, `name`, `username`, `email`, `password`, `bio`, `avatar_path`, `is_private`
- **Relacionamentos**:
  - `posts()` — posts publicados pelo usuário
  - `likes()` — posts curtidos
  - `saves()` — posts salvos
  - `reposts()` — posts republicados
  - `stories()` — stories ativos
  - `comments()` — comentários feitos
  - `following()` — usuários que este usuário segue (status accepted)
  - `followers()` — usuários que seguem este usuário (status accepted)
  - `pendingFollowers()` — solicitações de seguimento pendentes
  - `notifications()` — notificações recebidas
- **Computed**: `avatar_url` — URL pública do avatar ou null

---

### Post.php
Representa um post (imagem ou vídeo + legenda).

- **Campos**: `id`, `user_id`, `image_path`, `caption`
- **Relacionamentos**: `user()`, `likes()`, `saves()`, `reposts()`, `comments()`
- **Computed**: `image_url`, `is_video` (verifica extensão .mp4)
- **Scopes**: `withPostCounts()`, `withLikedByViewer()`, `withSavedByViewer()`, `withRepostedByViewer()` — carregam contagens e estado do viewer com uma query

---

### Comment.php
Comentário em um post.

- **Campos**: `id`, `user_id`, `post_id`, `body`
- **Relacionamentos**: `user()`, `post()`

---

### Like.php / Save.php / Repost.php
Curtida, salvamento e republicação de posts.

- **Chave primária composta**: `(user_id, post_id)` — impede duplicatas no banco
- Não têm `updated_at`

---

### Story.php
Conteúdo que expira automaticamente após 24 horas.

- **Campos**: `id`, `user_id`, `image_path`
- **Relacionamentos**: `user()`, `views()` (StoryView)
- **Computed**: `image_url`, `is_video`, `expires_at` (created_at + 24h)
- **Scope**: `active()` — filtra stories ainda dentro das 24h

---

### StoryView.php
Registro de visualização de um story.

- **Campos**: `user_id`, `story_id`
- Sem timestamps

---

### Notification.php
Notificação de atividade (curtida, comentário, follow, solicitação de follow).

- **Campos**: `id`, `user_id`, `type`, `data` (JSON), `read_at`
- `data` é automaticamente convertido de/para array pelo Laravel
- Não tem `updated_at`

---

## Routes (routes/api.php)

Todas as rotas começam com `/api/`. As protegidas exigem `Authorization: Bearer {token}`.

### Autenticação

| Método | Rota | Proteção | O que faz |
|---|---|---|---|
| POST | `/auth/register` | Pública (throttle 10/min) | Cria conta e retorna token |
| POST | `/auth/login` | Pública (throttle 10/min) | Autentica e retorna token |
| POST | `/auth/refresh` | Autenticada | Revoga token atual e emite novo |
| POST | `/auth/logout` | Autenticada | Revoga token |
| GET | `/auth/me` | Autenticada | Retorna usuário autenticado com contagens |

### Feed

| Método | Rota | O que faz |
|---|---|---|
| GET | `/feed` | Feed paginado por cursor dos usuários seguidos (ordem: mais recente) |

### Stories

| Método | Rota | O que faz |
|---|---|---|
| GET | `/stories/feed` | Stories ativos agrupados por usuário (próprio primeiro, não vistos primeiro) |
| POST | `/stories` | Cria story (upload de imagem/vídeo) |
| POST | `/stories/{story}/seen` | Marca story como visto |
| DELETE | `/stories/{story}` | Deleta story (apenas dono) |

### Posts

| Método | Rota | O que faz |
|---|---|---|
| GET | `/posts/explore` | Posts de descoberta (respeita privacidade) |
| POST | `/posts` | Cria post (upload de imagem/vídeo + legenda) |
| GET | `/posts/{post}` | Retorna post com contagens e estado do viewer |
| PUT | `/posts/{post}` | Atualiza legenda (apenas dono) |
| DELETE | `/posts/{post}` | Deleta post e arquivo (apenas dono) |

### Engajamento

| Método | Rota | O que faz |
|---|---|---|
| POST | `/posts/{post}/like` | Curte post (notifica dono) |
| DELETE | `/posts/{post}/like` | Remove curtida |
| GET | `/posts/{post}/likes` | Lista usuários que curtiram |
| POST | `/posts/{post}/save` | Salva post |
| DELETE | `/posts/{post}/save` | Remove dos salvos |
| POST | `/posts/{post}/repost` | Republica post (não funciona em post próprio) |
| DELETE | `/posts/{post}/repost` | Desfaz republicação |
| POST | `/posts/{post}/comments` | Cria comentário (notifica dono) |
| GET | `/posts/{post}/comments` | Lista comentários (mais recentes primeiro) |
| PUT | `/comments/{comment}` | Edita comentário (apenas autor) |
| DELETE | `/comments/{comment}` | Deleta comentário (apenas autor) |

### Perfil

| Método | Rota | O que faz |
|---|---|---|
| GET | `/users/me` | Perfil do usuário autenticado |
| PUT | `/users/me` | Atualiza nome, username, bio |
| POST | `/users/me/avatar` | Atualiza avatar |
| PUT | `/users/me/privacy` | Alterna perfil público/privado |
| DELETE | `/users/me` | Deleta conta permanentemente |
| GET | `/users/me/saved` | Posts salvos do usuário |
| GET | `/users/search` | Busca usuários por nome/username |
| GET | `/users/suggestions` | Sugestões de perfis para seguir |
| GET | `/users/{username}` | Perfil público de um usuário |
| GET | `/users/{user}/posts` | Posts de um usuário (respeita privacidade) |
| GET | `/users/{user}/reposts` | Republicações de um usuário |

### Follows

| Método | Rota | O que faz |
|---|---|---|
| POST | `/users/{user}/follow` | Segue usuário (pending se privado, accepted se público) |
| DELETE | `/users/{user}/follow` | Para de seguir |
| DELETE | `/users/{user}/followers` | Remove um seguidor |
| POST | `/users/{user}/follow/accept` | Aceita solicitação de follow |
| POST | `/users/{user}/follow/decline` | Rejeita solicitação de follow |
| GET | `/users/{user}/followers` | Lista seguidores |
| GET | `/users/{user}/following` | Lista seguindo |
| GET | `/users/{user}/is-following` | Retorna `{is_following, is_pending}` |

### Notificações

| Método | Rota | O que faz |
|---|---|---|
| GET | `/notifications` | Lista notificações (paginadas) |
| GET | `/notifications/unread-count` | Quantidade de não lidas |
| PUT | `/notifications/read` | Marca todas como lidas |

---

## Controllers (app/Http/Controllers/)

Os controllers recebem a requisição HTTP, delegam para o Service adequado e retornam o Resource formatado.

| Controller | Responsabilidade |
|---|---|
| `AuthController` | Registro, login, logout, refresh, me |
| `PostController` | CRUD de posts |
| `FeedController` | Feed paginado |
| `StoryController` | CRUD de stories + feed de stories |
| `FollowController` | Seguir, deixar de seguir, aceitar/recusar, listagens |
| `ProfileController` | Visualizar e editar perfil, busca, sugestões |
| `CommentController` | CRUD de comentários |
| `LikeController` | Curtir, descurtir, listar quem curtiu |
| `SaveController` | Salvar, remover salvos, listar salvos |
| `RepostController` | Republicar, desfazer, listar republicações |
| `NotificationController` | Listar, contar não lidas, marcar como lidas |
| `ExploreController` | Posts de descoberta |

---

## Services (app/Services/)

Os services contêm a lógica de negócio. Os controllers chamam os services; os services interagem com os models.

### AuthService
Cria usuário + emite token no registro. Valida credenciais e emite token no login. Revoga tokens no logout e refresh.

### PostService
- `create()` — Salva arquivo com UUID no storage, cria registro no banco
- `delete()` — Remove arquivo do storage + registro do banco
- `byUser()` — Respeita privacidade: se o perfil é privado, só retorna posts para seguidores
- `explore()` — Posts públicos ou de usuários que o viewer segue

### FeedService
Retorna posts paginados por cursor dos usuários que o viewer segue (status accepted), ordenados por `created_at DESC, id DESC`.

### FollowService
- `follow()` — Insere atomicamente. Se o alvo tem `is_private=true`, status = `pending`; caso contrário, `accepted`. Notifica o alvo.
- `acceptRequest()` — Muda status para `accepted` e deleta a notificação de solicitação.
- `declineRequest()` — Deleta o follow pendente e a notificação.

### UserService
- `updateAvatar()` — Deleta avatar antigo do storage antes de salvar o novo
- `deleteAccount()` — Deleta todos os arquivos de mídia do usuário e depois o registro

### CommentService / LikeService / SaveService / StoryService
Cada um encapsula o CRUD + interação com storage quando necessário.

### NotificationService
- `notify()` — Cria notificação. Não cria auto-notificações (usuário notificando a si mesmo). Deleta notificações duplicadas de `follow_request` do mesmo ator antes de criar nova.
- `markAllRead()` — Atualiza `read_at = now()` em todas as notificações não lidas do usuário.

---

## HTTP Requests — Validação (app/Http/Requests/)

Cada Request valida os dados antes de chegarem ao controller.

| Request | Valida |
|---|---|
| `RegisterRequest` | name, username (regex alfanumérico + . _), email único, password (mín 8, confirmado) |
| `LoginRequest` | email, password |
| `StorePostRequest` | image (jpeg/png/webp/gif/mp4, máx 100MB), caption (máx 2200 chars) |
| `UpdatePostRequest` | caption (deve estar presente, pode ser null) |
| `StoreCommentRequest` | body (1–500 chars) |
| `UpdateCommentRequest` | body (1–500 chars) |
| `StoreStoryRequest` | image (jpeg/png/webp/gif/mp4, máx 50MB) |
| `UpdateProfileRequest` | name, username (único ignorando próprio), bio (máx 500) |
| `UpdateAvatarRequest` | avatar (jpeg/png/webp, máx 5MB) |

---

## HTTP Resources — Resposta JSON (app/Http/Resources/)

Os resources formatam os dados do banco em JSON para o frontend.

### UserResource
```json
{
  "id": 1,
  "name": "Leonardo",
  "username": "leo",
  "email": "leo@email.com",        // só retorna para o próprio usuário
  "bio": "Sobre mim...",
  "avatar_url": "https://...",
  "is_private": false,
  "posts_count": 12,
  "followers_count": 340,
  "following_count": 150,
  "is_following": true,            // omitido se for o próprio usuário
  "is_follow_pending": false,      // omitido se for o próprio usuário
  "created_at": "2026-04-23T..."
}
```

### PostResource
```json
{
  "id": 42,
  "image_url": "https://...",
  "is_video": false,
  "caption": "Legenda do post",
  "created_at": "2026-04-25T...",
  "author": { ...UserResource },
  "likes_count": 87,
  "comments_count": 14,
  "reposts_count": 3,
  "liked_by_me": true,
  "saved_by_me": false,
  "reposted_by_me": false
}
```

### NotificationResource
```json
{
  "id": 99,
  "type": "like",                  // "like" | "comment" | "follow" | "follow_request"
  "data": {
    "actor_id": 7,
    "actor_name": "Maria",
    "actor_username": "maria",
    "actor_avatar_url": "https://...",
    "post_id": 42,
    "post_image_url": "https://...",
    "post_is_video": false
  },
  "read_at": null,
  "created_at": "2026-04-26T..."
}
```

---

## Policies — Autorização (app/Policies/)

As policies definem quem pode executar cada ação.

| Policy | Regra |
|---|---|
| `PostPolicy::modify` | Só o dono do post pode editar ou deletar |
| `CommentPolicy::modify` | Só o autor do comentário pode editar ou deletar |
| `StoryPolicy::delete` | Só o dono do story pode deletar |

---

## Observers — Eventos de Model (app/Observers/)

Os observers reagem automaticamente a eventos dos models sem poluir os services.

### LikeObserver
Disparado quando um `Like` é criado. Envia notificação do tipo `like` para o dono do post com dados do ator e do post. Ignora se o ator é o próprio dono.

### CommentObserver
Disparado quando um `Comment` é criado. Envia notificação do tipo `comment` para o dono do post com dados do ator, post e comentário. Ignora se o comentarista é o próprio dono do post.

Os observers são registrados no `AppServiceProvider`:
```php
Like::observe(LikeObserver::class);
Comment::observe(CommentObserver::class);
```

---

## Exceptions (app/Exceptions/)

### SelfFollowException
Lançada pelo `FollowService` quando o usuário tenta se seguir. Retorna HTTP 422 com a mensagem "Você não pode seguir a si mesmo."

---

## Bootstrap (bootstrap/app.php)

Ponto central de configuração da aplicação:

- **Rotas**: prefixo `/api`, arquivo `routes/api.php`, health check em `/up`
- **Middleware**: `trustProxies('*')` — necessário para o Cloud Run gerar URLs HTTPS corretamente
- **Tratamento de erros**: Converte exceções PHP em respostas JSON padronizadas em português

---

## Docker — Inicialização do Container

### entrypoint.sh
Script executado toda vez que o container inicia. Ordem de execução:

1. **Cria `.env`** se não existir (copia de `.env.example`)
2. **Gera `APP_KEY`** se não estiver definida
3. **Aguarda MySQL** ficar disponível (até 60 tentativas com 1s de intervalo)
4. **Executa migrations** (`php artisan migrate --force`) se `RUN_MIGRATIONS=true`
5. **Cria symlink** de `storage/public` para `public/storage`
6. **Em produção**: cacheia rotas e eventos para performance
7. **Inicia FrankenPHP**

### php.ini
Configurações relevantes:
- `upload_max_filesize = 64M` — permite uploads de até 64MB
- `post_max_size = 64M`
- `max_execution_time = 120s`
- OPcache ativado com JIT para performance máxima

---

## Regras de Negócio Importantes

### Privacidade de Perfil
- `is_private = true`: novas solicitações de follow ficam com status `pending`
- Ao mudar de privado para público: todas as solicitações pendentes são auto-aceitas
- Posts de contas privadas só aparecem para seguidores aceitos ou para o próprio usuário

### Idempotência
- `like`, `save`, `repost` e `story_view` usam `firstOrCreate` ou `insertOrIgnore` — chamar duas vezes não duplica o registro

### Notificações
- Não são geradas auto-notificações (usuário não se notifica)
- Nova solicitação de follow do mesmo ator deleta a anterior antes de criar
- Notificações de `follow_request` são deletadas ao aceitar ou recusar

### Armazenamento de Mídia
- Posts: `storage/app/public/posts/{uuid}.{ext}`
- Avatars: `storage/app/public/avatars/{uuid}.{ext}`
- Stories: `storage/app/public/stories/{uuid}.{ext}`
- Servidos publicamente via symlink em `public/storage/`
- UUIDs garantem nomes únicos e evitam colisões

### Paginação
- **Cursor**: Feed principal (evita duplicatas em listas que mudam frequentemente)
- **Offset**: Demais endpoints (comentários, seguidores, salvos etc.)

---

## Segurança

| Ponto | Implementação |
|---|---|
| Senhas | Hash bcrypt (fator 12) — irreversível mesmo com acesso ao banco |
| Autenticação | Tokens Bearer Sanctum — revogados explicitamente no logout |
| Expiração de token | 7 dias (`expiration: 10080` em sanctum.php) |
| Renovação automática | Frontend intercepta 401 e chama `/auth/refresh` transparentemente; singleton previne refreshes paralelos |
| Rate limiting | Login/registro: 10 req/min; rotas autenticadas: 300 req/min |
| Autorização | Policies verificadas em toda operação destrutiva (editar/deletar post, comentário, story) |
| Mass assignment | `$fillable` explícito em todos os models; `$hidden` oculta password e campos sensíveis |
| SQL Injection | Eloquent com bindings preparados em todas as queries; LIKE escapa `%`, `_`, `\` via `addcslashes()` |
| Upload de arquivos | MIME validado, tamanho limitado, nome substituído por UUID — nunca usa nome original do cliente |
| CORS | Apenas origens definidas em `CORS_ALLOWED_ORIGINS` são aceitas |
| HTTPS | Garantido pela infraestrutura (Cloud Run termina SSL na borda) |
| Privacidade | Posts de contas privadas filtrados no Service — não depende do frontend |

---

## Variáveis de Ambiente (Produção)

| Variável | Propósito |
|---|---|
| `APP_KEY` | Chave de criptografia da aplicação |
| `APP_ENV` | `production` |
| `APP_URL` | URL base do backend (para geração de URLs) |
| `DB_SOCKET` | Socket Unix do Cloud SQL |
| `DB_DATABASE` | Nome do banco |
| `DB_USERNAME` | Usuário do banco |
| `DB_PASSWORD` | Senha do banco |
| `CORS_ALLOWED_ORIGINS` | URL do frontend (permite requisições cross-origin) |
