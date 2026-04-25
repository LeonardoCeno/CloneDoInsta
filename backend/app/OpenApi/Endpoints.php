<?php

namespace App\OpenApi;

/**
 * @OA\Post(path="/auth/register", tags={"Auth"}, summary="Criar conta",
 *     @OA\RequestBody(required=true,
 *         @OA\JsonContent(required={"name","username","email","password","password_confirmation"},
 *             @OA\Property(property="name", type="string", maxLength=255, example="Alice"),
 *             @OA\Property(property="username", type="string", maxLength=30, example="alice"),
 *             @OA\Property(property="email", type="string", format="email", example="alice@example.com"),
 *             @OA\Property(property="password", type="string", minLength=8, example="password"),
 *             @OA\Property(property="password_confirmation", type="string", example="password")
 *         )
 *     ),
 *     @OA\Response(response=201, description="Conta criada",
 *         @OA\JsonContent(
 *             @OA\Property(property="user", ref="#/components/schemas/User"),
 *             @OA\Property(property="access_token", type="string")
 *         )
 *     ),
 *     @OA\Response(response=422, ref="#/components/responses/422")
 * )
 *
 * @OA\Post(path="/auth/login", tags={"Auth"}, summary="Autenticar",
 *     @OA\RequestBody(required=true,
 *         @OA\JsonContent(required={"email","password"},
 *             @OA\Property(property="email", type="string", format="email", example="alice@example.com"),
 *             @OA\Property(property="password", type="string", example="password")
 *         )
 *     ),
 *     @OA\Response(response=200, description="Autenticado",
 *         @OA\JsonContent(
 *             @OA\Property(property="user", ref="#/components/schemas/User"),
 *             @OA\Property(property="access_token", type="string")
 *         )
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/401")
 * )
 *
 * @OA\Post(path="/auth/refresh", tags={"Auth"}, summary="Renovar token",
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(response=200, description="Token renovado",
 *         @OA\JsonContent(
 *             @OA\Property(property="user", ref="#/components/schemas/User"),
 *             @OA\Property(property="access_token", type="string")
 *         )
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/401")
 * )
 *
 * @OA\Post(path="/auth/logout", tags={"Auth"}, summary="Encerrar sessão",
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(response=200, description="Logout realizado",
 *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Logout realizado com sucesso."))
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/401")
 * )
 *
 * @OA\Get(path="/auth/me", tags={"Auth"}, summary="Usuário autenticado",
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(response=200, description="Dados do usuário autenticado",
 *         @OA\JsonContent(ref="#/components/schemas/User")
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/401")
 * )
 *
 * @OA\Get(path="/users/{username}", tags={"Usuários"}, summary="Perfil por username",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="username", in="path", required=true, @OA\Schema(type="string"), example="alice"),
 *     @OA\Response(response=200, description="Perfil do usuário",
 *         @OA\JsonContent(ref="#/components/schemas/User")
 *     ),
 *     @OA\Response(response=404, ref="#/components/responses/404")
 * )
 *
 * @OA\Get(path="/users/me", tags={"Usuários"}, summary="Meu perfil",
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(response=200, description="Perfil do usuário autenticado",
 *         @OA\JsonContent(ref="#/components/schemas/User")
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/401")
 * )
 *
 * @OA\Put(path="/users/me", tags={"Usuários"}, summary="Atualizar perfil",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="name", type="string", maxLength=255, example="Alice Silva"),
 *             @OA\Property(property="bio", type="string", maxLength=500, nullable=true, example="Nova bio")
 *         )
 *     ),
 *     @OA\Response(response=200, description="Perfil atualizado",
 *         @OA\JsonContent(ref="#/components/schemas/User")
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/401"),
 *     @OA\Response(response=422, ref="#/components/responses/422")
 * )
 *
 * @OA\Post(path="/users/me/avatar", tags={"Usuários"}, summary="Atualizar avatar",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(required=true,
 *         @OA\MediaType(mediaType="multipart/form-data",
 *             @OA\Schema(required={"avatar"},
 *                 @OA\Property(property="avatar", type="string", format="binary", description="JPEG, PNG ou WebP, máx 5MB")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=200, description="Avatar atualizado",
 *         @OA\JsonContent(ref="#/components/schemas/User")
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/401"),
 *     @OA\Response(response=422, ref="#/components/responses/422")
 * )
 *
 * @OA\Get(path="/users/suggestions", tags={"Usuários"}, summary="Sugestões de usuários para seguir",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=20, maximum=100)),
 *     @OA\Response(response=200, description="Lista paginada de sugestões",
 *         @OA\JsonContent(ref="#/components/schemas/PaginatedUsers")
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/401")
 * )
 *
 * @OA\Get(path="/users/search", tags={"Usuários"}, summary="Buscar usuários",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="q", in="query", required=true, @OA\Schema(type="string", minLength=1, maxLength=50), example="alice"),
 *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=15, maximum=100)),
 *     @OA\Response(response=200, description="Resultado da busca",
 *         @OA\JsonContent(ref="#/components/schemas/PaginatedUsers")
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/401"),
 *     @OA\Response(response=422, ref="#/components/responses/422")
 * )
 *
 * @OA\Get(path="/users/{user}/posts", tags={"Usuários"}, summary="Posts de um usuário",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="user", in="path", required=true, @OA\Schema(type="integer"), example=1),
 *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=15, maximum=100)),
 *     @OA\Response(response=200, description="Posts paginados",
 *         @OA\JsonContent(ref="#/components/schemas/PaginatedPosts")
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/401"),
 *     @OA\Response(response=404, ref="#/components/responses/404")
 * )
 *
 * @OA\Post(path="/users/{user}/follow", tags={"Social"}, summary="Seguir usuário",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="user", in="path", required=true, @OA\Schema(type="integer"), example=2),
 *     @OA\Response(response=200, description="Seguindo",
 *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Seguindo."))
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/401"),
 *     @OA\Response(response=422, description="Tentativa de seguir a si mesmo",
 *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Você não pode seguir a si mesmo."))
 *     )
 * )
 *
 * @OA\Delete(path="/users/{user}/follow", tags={"Social"}, summary="Deixar de seguir usuário",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="user", in="path", required=true, @OA\Schema(type="integer"), example=2),
 *     @OA\Response(response=200, description="Deixou de seguir",
 *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Deixou de seguir."))
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/401")
 * )
 *
 * @OA\Get(path="/users/{user}/followers", tags={"Social"}, summary="Seguidores de um usuário",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="user", in="path", required=true, @OA\Schema(type="integer"), example=1),
 *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=20, maximum=100)),
 *     @OA\Response(response=200, description="Seguidores paginados",
 *         @OA\JsonContent(ref="#/components/schemas/PaginatedUsers")
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/401"),
 *     @OA\Response(response=404, ref="#/components/responses/404")
 * )
 *
 * @OA\Get(path="/users/{user}/following", tags={"Social"}, summary="Usuários que um usuário segue",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="user", in="path", required=true, @OA\Schema(type="integer"), example=1),
 *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=20, maximum=100)),
 *     @OA\Response(response=200, description="Following paginado",
 *         @OA\JsonContent(ref="#/components/schemas/PaginatedUsers")
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/401"),
 *     @OA\Response(response=404, ref="#/components/responses/404")
 * )
 *
 * @OA\Get(path="/users/{user}/is-following", tags={"Social"}, summary="Verificar se segue um usuário",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="user", in="path", required=true, @OA\Schema(type="integer"), example=2),
 *     @OA\Response(response=200, description="Status de follow",
 *         @OA\JsonContent(@OA\Property(property="is_following", type="boolean", example=true))
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/401")
 * )
 *
 * @OA\Post(path="/posts", tags={"Posts"}, summary="Criar post",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(required=true,
 *         @OA\MediaType(mediaType="multipart/form-data",
 *             @OA\Schema(required={"image"},
 *                 @OA\Property(property="image", type="string", format="binary", description="JPEG, PNG ou WebP, máx 10MB"),
 *                 @OA\Property(property="caption", type="string", maxLength=2200, nullable=true, example="Meu post!")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=201, description="Post criado",
 *         @OA\JsonContent(ref="#/components/schemas/Post")
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/401"),
 *     @OA\Response(response=422, ref="#/components/responses/422")
 * )
 *
 * @OA\Get(path="/posts/{post}", tags={"Posts"}, summary="Detalhes de um post",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="post", in="path", required=true, @OA\Schema(type="integer"), example=1),
 *     @OA\Response(response=200, description="Post encontrado",
 *         @OA\JsonContent(ref="#/components/schemas/Post")
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/401"),
 *     @OA\Response(response=404, ref="#/components/responses/404")
 * )
 *
 * @OA\Put(path="/posts/{post}", tags={"Posts"}, summary="Editar legenda do post",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="post", in="path", required=true, @OA\Schema(type="integer"), example=1),
 *     @OA\RequestBody(required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="caption", type="string", maxLength=2200, nullable=true, example="Legenda atualizada")
 *         )
 *     ),
 *     @OA\Response(response=200, description="Post atualizado",
 *         @OA\JsonContent(ref="#/components/schemas/Post")
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/401"),
 *     @OA\Response(response=403, ref="#/components/responses/403"),
 *     @OA\Response(response=404, ref="#/components/responses/404")
 * )
 *
 * @OA\Delete(path="/posts/{post}", tags={"Posts"}, summary="Remover post",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="post", in="path", required=true, @OA\Schema(type="integer"), example=1),
 *     @OA\Response(response=200, description="Post removido",
 *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Post removido."))
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/401"),
 *     @OA\Response(response=403, ref="#/components/responses/403"),
 *     @OA\Response(response=404, ref="#/components/responses/404")
 * )
 *
 * @OA\Get(path="/feed", tags={"Feed"}, summary="Feed de posts dos usuários seguidos",
 *     description="Retorna posts em cursor pagination. Use `cursor` para paginar.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="cursor", in="query", @OA\Schema(type="string"), description="Cursor da página anterior"),
 *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=15, maximum=50)),
 *     @OA\Response(response=200, description="Feed com cursor pagination",
 *         @OA\JsonContent(ref="#/components/schemas/CursorPaginatedPosts")
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/401")
 * )
 *
 * @OA\Post(path="/posts/{post}/like", tags={"Likes"}, summary="Curtir post",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="post", in="path", required=true, @OA\Schema(type="integer"), example=1),
 *     @OA\Response(response=200, description="Post curtido",
 *         @OA\JsonContent(
 *             @OA\Property(property="liked", type="boolean", example=true),
 *             @OA\Property(property="likes_count", type="integer", example=43)
 *         )
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/401"),
 *     @OA\Response(response=404, ref="#/components/responses/404")
 * )
 *
 * @OA\Delete(path="/posts/{post}/like", tags={"Likes"}, summary="Descurtir post",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="post", in="path", required=true, @OA\Schema(type="integer"), example=1),
 *     @OA\Response(response=200, description="Post descurtido",
 *         @OA\JsonContent(
 *             @OA\Property(property="liked", type="boolean", example=false),
 *             @OA\Property(property="likes_count", type="integer", example=42)
 *         )
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/401"),
 *     @OA\Response(response=404, ref="#/components/responses/404")
 * )
 *
 * @OA\Get(path="/posts/{post}/likes", tags={"Likes"}, summary="Usuários que curtiram o post",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="post", in="path", required=true, @OA\Schema(type="integer"), example=1),
 *     @OA\Response(response=200, description="Lista de usuários",
 *         @OA\JsonContent(ref="#/components/schemas/PaginatedUsers")
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/401"),
 *     @OA\Response(response=404, ref="#/components/responses/404")
 * )
 *
 * @OA\Post(path="/posts/{post}/save", tags={"Salvos"}, summary="Salvar post",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="post", in="path", required=true, @OA\Schema(type="integer"), example=1),
 *     @OA\Response(response=200, description="Post salvo",
 *         @OA\JsonContent(@OA\Property(property="saved", type="boolean", example=true))
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/401"),
 *     @OA\Response(response=404, ref="#/components/responses/404")
 * )
 *
 * @OA\Delete(path="/posts/{post}/save", tags={"Salvos"}, summary="Remover post dos salvos",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="post", in="path", required=true, @OA\Schema(type="integer"), example=1),
 *     @OA\Response(response=200, description="Post removido dos salvos",
 *         @OA\JsonContent(@OA\Property(property="saved", type="boolean", example=false))
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/401"),
 *     @OA\Response(response=404, ref="#/components/responses/404")
 * )
 *
 * @OA\Get(path="/users/me/saved", tags={"Salvos"}, summary="Posts salvos pelo usuário autenticado",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=15, maximum=100)),
 *     @OA\Response(response=200, description="Posts salvos paginados",
 *         @OA\JsonContent(ref="#/components/schemas/PaginatedPosts")
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/401")
 * )
 *
 * @OA\Post(path="/posts/{post}/comments", tags={"Comentários"}, summary="Comentar em um post",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="post", in="path", required=true, @OA\Schema(type="integer"), example=1),
 *     @OA\RequestBody(required=true,
 *         @OA\JsonContent(required={"body"},
 *             @OA\Property(property="body", type="string", minLength=1, maxLength=500, example="Que foto incrível!")
 *         )
 *     ),
 *     @OA\Response(response=201, description="Comentário criado",
 *         @OA\JsonContent(ref="#/components/schemas/Comment")
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/401"),
 *     @OA\Response(response=404, ref="#/components/responses/404"),
 *     @OA\Response(response=422, ref="#/components/responses/422")
 * )
 *
 * @OA\Get(path="/posts/{post}/comments", tags={"Comentários"}, summary="Comentários de um post",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="post", in="path", required=true, @OA\Schema(type="integer"), example=1),
 *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=20, maximum=100)),
 *     @OA\Response(response=200, description="Comentários paginados",
 *         @OA\JsonContent(ref="#/components/schemas/PaginatedComments")
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/401"),
 *     @OA\Response(response=404, ref="#/components/responses/404")
 * )
 *
 * @OA\Put(path="/comments/{comment}", tags={"Comentários"}, summary="Editar comentário",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="comment", in="path", required=true, @OA\Schema(type="integer"), example=1),
 *     @OA\RequestBody(required=true,
 *         @OA\JsonContent(required={"body"},
 *             @OA\Property(property="body", type="string", minLength=1, maxLength=500, example="Comentário editado")
 *         )
 *     ),
 *     @OA\Response(response=200, description="Comentário atualizado",
 *         @OA\JsonContent(ref="#/components/schemas/Comment")
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/401"),
 *     @OA\Response(response=403, ref="#/components/responses/403"),
 *     @OA\Response(response=404, ref="#/components/responses/404"),
 *     @OA\Response(response=422, ref="#/components/responses/422")
 * )
 *
 * @OA\Delete(path="/comments/{comment}", tags={"Comentários"}, summary="Remover comentário",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="comment", in="path", required=true, @OA\Schema(type="integer"), example=1),
 *     @OA\Response(response=200, description="Comentário removido",
 *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Comentário removido."))
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/401"),
 *     @OA\Response(response=403, ref="#/components/responses/403"),
 *     @OA\Response(response=404, ref="#/components/responses/404")
 * )
 *
 * @OA\Get(path="/notifications", tags={"Notificações"}, summary="Listar notificações",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=20, maximum=100)),
 *     @OA\Response(response=200, description="Notificações paginadas",
 *         @OA\JsonContent(ref="#/components/schemas/PaginatedNotifications")
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/401")
 * )
 *
 * @OA\Get(path="/notifications/unread-count", tags={"Notificações"}, summary="Total de notificações não lidas",
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(response=200, description="Contagem de não lidas",
 *         @OA\JsonContent(@OA\Property(property="count", type="integer", example=3))
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/401")
 * )
 *
 * @OA\Put(path="/notifications/read", tags={"Notificações"}, summary="Marcar todas as notificações como lidas",
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(response=200, description="Notificações marcadas como lidas",
 *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Notificações marcadas como lidas."))
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/401")
 * )
 *
 * @OA\Get(path="/posts/explore", tags={"Posts"}, summary="Explorar posts de toda a rede",
 *     description="Retorna posts de todos os usuários (não apenas seguidos), útil para a tela Explorar.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=18, maximum=100)),
 *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", default=1)),
 *     @OA\Response(response=200, description="Posts paginados",
 *         @OA\JsonContent(ref="#/components/schemas/PaginatedPosts")
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/401")
 * )
 *
 * @OA\Post(path="/posts/{post}/repost", tags={"Republicações"}, summary="Republicar post",
 *     description="Adiciona o post à seção de republicações do usuário autenticado. Não é possível republicar o próprio post.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="post", in="path", required=true, @OA\Schema(type="integer"), example=1),
 *     @OA\Response(response=200, description="Post republicado",
 *         @OA\JsonContent(@OA\Property(property="reposted", type="boolean", example=true))
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/401"),
 *     @OA\Response(response=404, ref="#/components/responses/404"),
 *     @OA\Response(response=422, description="Tentativa de republicar o próprio post",
 *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Não é possível repostar o próprio post."))
 *     )
 * )
 *
 * @OA\Delete(path="/posts/{post}/repost", tags={"Republicações"}, summary="Remover republicação",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="post", in="path", required=true, @OA\Schema(type="integer"), example=1),
 *     @OA\Response(response=200, description="Republicação removida",
 *         @OA\JsonContent(@OA\Property(property="reposted", type="boolean", example=false))
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/401"),
 *     @OA\Response(response=404, ref="#/components/responses/404")
 * )
 *
 * @OA\Get(path="/users/{user}/reposts", tags={"Republicações"}, summary="Republicações de um usuário",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="user", in="path", required=true, @OA\Schema(type="integer"), example=1),
 *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=15, maximum=100)),
 *     @OA\Response(response=200, description="Posts republicados paginados",
 *         @OA\JsonContent(ref="#/components/schemas/PaginatedPosts")
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/401"),
 *     @OA\Response(response=404, ref="#/components/responses/404")
 * )
 *
 * @OA\Get(path="/stories/feed", tags={"Stories"}, summary="Stories dos usuários seguidos",
 *     description="Retorna grupos de stories agrupados por usuário. Os próprios stories do usuário autenticado aparecem primeiro.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(response=200, description="Grupos de stories",
 *         @OA\JsonContent(
 *             type="array",
 *             @OA\Items(
 *                 @OA\Property(property="user", ref="#/components/schemas/User"),
 *                 @OA\Property(property="has_unseen", type="boolean", example=true),
 *                 @OA\Property(property="stories", type="array",
 *                     @OA\Items(ref="#/components/schemas/Story")
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/401")
 * )
 *
 * @OA\Post(path="/stories", tags={"Stories"}, summary="Publicar story",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(required=true,
 *         @OA\MediaType(mediaType="multipart/form-data",
 *             @OA\Schema(required={"image"},
 *                 @OA\Property(property="image", type="string", format="binary", description="JPEG, PNG ou WebP, máx 10MB")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=201, description="Story publicado",
 *         @OA\JsonContent(ref="#/components/schemas/Story")
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/401"),
 *     @OA\Response(response=422, ref="#/components/responses/422")
 * )
 *
 * @OA\Post(path="/stories/{story}/seen", tags={"Stories"}, summary="Marcar story como visto",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="story", in="path", required=true, @OA\Schema(type="integer"), example=1),
 *     @OA\Response(response=200, description="Story marcado como visto",
 *         @OA\JsonContent(@OA\Property(property="seen", type="boolean", example=true))
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/401"),
 *     @OA\Response(response=404, ref="#/components/responses/404")
 * )
 *
 * @OA\Delete(path="/stories/{story}", tags={"Stories"}, summary="Remover story",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="story", in="path", required=true, @OA\Schema(type="integer"), example=1),
 *     @OA\Response(response=200, description="Story removido",
 *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Story removido."))
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/401"),
 *     @OA\Response(response=403, ref="#/components/responses/403"),
 *     @OA\Response(response=404, ref="#/components/responses/404")
 * )
 */
class Endpoints {}
