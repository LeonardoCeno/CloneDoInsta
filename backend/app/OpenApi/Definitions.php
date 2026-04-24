<?php

namespace App\OpenApi;

/**
 * @OA\Info(
 *     title="InstaClone API",
 *     version="1.0.0",
 *     description="REST API para a plataforma InstaClone. Autenticação via Bearer token (Sanctum)."
 * )
 *
 * @OA\Server(url="/api", description="API")
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 *
 * ---------------------------------------------------------------------------
 * Schemas
 * ---------------------------------------------------------------------------
 *
 * @OA\Schema(
 *     schema="User",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Alice"),
 *     @OA\Property(property="username", type="string", example="alice"),
 *     @OA\Property(property="email", type="string", format="email", example="alice@example.com", description="Visível apenas para o próprio usuário"),
 *     @OA\Property(property="bio", type="string", nullable=true, example="Fotógrafa"),
 *     @OA\Property(property="avatar_url", type="string", nullable=true, example="http://localhost:8000/storage/avatars/alice.jpg"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="posts_count", type="integer", example=12),
 *     @OA\Property(property="followers_count", type="integer", example=80),
 *     @OA\Property(property="following_count", type="integer", example=45),
 *     @OA\Property(property="is_following", type="boolean", nullable=true, example=false)
 * )
 *
 * @OA\Schema(
 *     schema="Post",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="image_url", type="string", example="http://localhost:8000/storage/posts/photo.jpg"),
 *     @OA\Property(property="caption", type="string", nullable=true, example="Pôr do sol incrível"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(property="user", ref="#/components/schemas/User"),
 *     @OA\Property(property="likes_count", type="integer", example=42),
 *     @OA\Property(property="comments_count", type="integer", example=7),
 *     @OA\Property(property="liked_by_me", type="boolean", nullable=true, example=false)
 * )
 *
 * @OA\Schema(
 *     schema="Comment",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="body", type="string", example="Que foto linda!"),
 *     @OA\Property(property="user_id", type="integer", example=2),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(property="user", ref="#/components/schemas/User")
 * )
 *
 * @OA\Schema(
 *     schema="Notification",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="type", type="string", enum={"like","comment","follow"}, example="like"),
 *     @OA\Property(property="data", type="object", description="Payload variável por tipo"),
 *     @OA\Property(property="read_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="PaginatedUsers",
 *     @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/User")),
 *     @OA\Property(property="links", type="object"),
 *     @OA\Property(property="meta", type="object")
 * )
 *
 * @OA\Schema(
 *     schema="PaginatedPosts",
 *     @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Post")),
 *     @OA\Property(property="links", type="object"),
 *     @OA\Property(property="meta", type="object")
 * )
 *
 * @OA\Schema(
 *     schema="PaginatedComments",
 *     @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Comment")),
 *     @OA\Property(property="links", type="object"),
 *     @OA\Property(property="meta", type="object")
 * )
 *
 * @OA\Schema(
 *     schema="PaginatedNotifications",
 *     @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Notification")),
 *     @OA\Property(property="links", type="object"),
 *     @OA\Property(property="meta", type="object")
 * )
 *
 * @OA\Schema(
 *     schema="CursorPaginatedPosts",
 *     @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Post")),
 *     @OA\Property(property="next_cursor", type="string", nullable=true),
 *     @OA\Property(property="next_page_url", type="string", nullable=true),
 *     @OA\Property(property="prev_cursor", type="string", nullable=true),
 *     @OA\Property(property="prev_page_url", type="string", nullable=true)
 * )
 *
 * ---------------------------------------------------------------------------
 * Reusable responses
 * ---------------------------------------------------------------------------
 *
 * @OA\Response(response="401", description="Não autenticado",
 *     @OA\JsonContent(@OA\Property(property="message", type="string", example="Unauthenticated."))
 * )
 *
 * @OA\Response(response="403", description="Sem permissão",
 *     @OA\JsonContent(@OA\Property(property="message", type="string", example="This action is unauthorized."))
 * )
 *
 * @OA\Response(response="404", description="Não encontrado",
 *     @OA\JsonContent(@OA\Property(property="message", type="string", example="No query results for model."))
 * )
 *
 * @OA\Response(response="422", description="Dados inválidos",
 *     @OA\JsonContent(
 *         @OA\Property(property="message", type="string", example="The given data was invalid."),
 *         @OA\Property(property="errors", type="object")
 *     )
 * )
 */
class Definitions {}
