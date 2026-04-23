# InstaClone Backend Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a production-grade Laravel 11 REST API for InstaClone with auth, users, posts, feed, likes, comments, notifications, and full Docker setup.

**Architecture:** Controller → Service → Model layering; FormRequest validation; Eloquent Policies for ownership; Eloquent Observers for notification side-effects; no repositories — Eloquent is the data layer.

**Tech Stack:** PHP 8.3, Laravel 11, Sanctum, MySQL 8, FrankenPHP, Docker multi-stage, Composer 2.

---

## File Map

```
instaclone-backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── AuthController.php
│   │   │   ├── UserController.php
│   │   │   ├── PostController.php
│   │   │   ├── FeedController.php
│   │   │   ├── LikeController.php
│   │   │   ├── CommentController.php
│   │   │   └── NotificationController.php
│   │   ├── Requests/
│   │   │   ├── RegisterRequest.php
│   │   │   ├── LoginRequest.php
│   │   │   ├── UpdateProfileRequest.php
│   │   │   ├── StorePostRequest.php
│   │   │   ├── UpdatePostRequest.php
│   │   │   └── StoreCommentRequest.php
│   │   └── Resources/
│   │       ├── UserResource.php
│   │       ├── PostResource.php
│   │       ├── CommentResource.php
│   │       └── NotificationResource.php
│   ├── Models/
│   │   ├── User.php
│   │   ├── Post.php
│   │   ├── Like.php
│   │   ├── Comment.php
│   │   └── Notification.php
│   ├── Policies/
│   │   ├── PostPolicy.php
│   │   └── CommentPolicy.php
│   ├── Observers/
│   │   ├── LikeObserver.php
│   │   ├── CommentObserver.php
│   │   └── FollowObserver.php  (pivot event via model)
│   └── Services/
│       ├── AuthService.php
│       ├── UserService.php
│       ├── FollowService.php
│       ├── PostService.php
│       ├── FeedService.php
│       ├── LikeService.php
│       ├── CommentService.php
│       └── NotificationService.php
├── database/
│   ├── migrations/
│   │   ├── 0001_01_01_000000_create_users_table.php  (modified)
│   │   ├── xxxx_create_follows_table.php
│   │   ├── xxxx_create_posts_table.php
│   │   ├── xxxx_create_likes_table.php
│   │   ├── xxxx_create_comments_table.php
│   │   └── xxxx_create_notifications_table.php
│   └── seeders/
│       ├── DatabaseSeeder.php
│       └── DemoSeeder.php
├── routes/
│   └── api.php
├── config/
│   └── cors.php  (modified)
├── docker/
│   ├── entrypoint.sh
│   └── php.ini
├── Dockerfile
├── compose.yaml
└── .env.example
```

---

## Task 1: Create Laravel Project

**Files:**
- Create: `instaclone-backend/` (entire project)

- [ ] **Step 1: Scaffold Laravel 11**

```bash
cd /home/leonardo/intheend
composer create-project laravel/laravel instaclone-backend --prefer-dist --no-interaction
cd instaclone-backend
```

- [ ] **Step 2: Install Sanctum**

```bash
cd /home/leonardo/intheend/instaclone-backend
composer require laravel/sanctum --no-interaction
```

- [ ] **Step 3: Remove unused default Laravel scaffolding**

```bash
cd /home/leonardo/intheend/instaclone-backend
# Remove unused frontend files
rm -f vite.config.js package.json resources/js/app.js resources/css/app.css 2>/dev/null || true
# Remove default test that references non-existent routes
rm -f tests/Feature/ExampleTest.php
```

- [ ] **Step 4: Initialize git**

```bash
cd /home/leonardo/intheend/instaclone-backend
git init
git add .
git commit -m "chore: initial Laravel 11 + Sanctum setup"
```

---

## Task 2: Configure Environment & Database

**Files:**
- Modify: `.env`
- Modify: `.env.example`
- Modify: `config/sanctum.php`
- Modify: `config/cors.php`
- Modify: `bootstrap/app.php`

- [ ] **Step 1: Configure .env for local dev**

Edit `/home/leonardo/intheend/instaclone-backend/.env`:
```
APP_NAME=InstaClone
APP_ENV=local
APP_KEY=  # will be generated
APP_DEBUG=true
APP_URL=http://localhost:8000

LOG_CHANNEL=stack
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=instaclone
DB_USERNAME=root
DB_PASSWORD=secret

FILESYSTEM_DISK=public

SANCTUM_STATEFUL_DOMAINS=localhost:5173,localhost:3000,localhost:8080
SESSION_DRIVER=file
```

- [ ] **Step 2: Write .env.example (docker-safe)**

```
APP_NAME=InstaClone
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=http://localhost:8000

LOG_CHANNEL=stderr
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=laravel
DB_PASSWORD=laravel

FILESYSTEM_DISK=public

SANCTUM_STATEFUL_DOMAINS=
SESSION_DRIVER=file

RUN_MIGRATIONS=true
```

- [ ] **Step 3: Configure CORS to accept frontend origin**

Replace content of `config/cors.php`:
```php
<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'storage/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173,http://localhost:3000,http://localhost:8080')),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
```

Add to `.env` and `.env.example`:
```
CORS_ALLOWED_ORIGINS=http://localhost:5173,http://localhost:3000
```

- [ ] **Step 4: Configure API middleware in bootstrap/app.php**

Replace `bootstrap/app.php` completely:
```php
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Os dados fornecidos são inválidos.',
                    'errors'  => $e->errors(),
                ], 422);
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Recurso não encontrado.'], 404);
            }
        });

        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Método não permitido.'], 405);
            }
        });
    })->create();
```

- [ ] **Step 5: Generate app key**

```bash
cd /home/leonardo/intheend/instaclone-backend
php artisan key:generate
```

- [ ] **Step 6: Commit**

```bash
cd /home/leonardo/intheend/instaclone-backend
git add -A
git commit -m "chore: configure env, cors, bootstrap exception handling"
```

---

## Task 3: Migrations

**Files:**
- Modify: `database/migrations/0001_01_01_000000_create_users_table.php`
- Create: `database/migrations/xxxx_create_follows_table.php`
- Create: `database/migrations/xxxx_create_posts_table.php`
- Create: `database/migrations/xxxx_create_likes_table.php`
- Create: `database/migrations/xxxx_create_comments_table.php`
- Create: `database/migrations/xxxx_create_notifications_table.php`

- [ ] **Step 1: Rewrite users migration**

Replace the `up()` method content of `database/migrations/0001_01_01_000000_create_users_table.php`:
```php
public function up(): void
{
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('username', 30)->unique();
        $table->string('email')->unique();
        $table->string('password');
        $table->string('bio', 160)->nullable();
        $table->string('avatar_path')->nullable();
        $table->rememberToken();
        $table->timestamps();
    });

    Schema::create('password_reset_tokens', function (Blueprint $table) {
        $table->string('email')->primary();
        $table->string('token');
        $table->timestamp('created_at')->nullable();
    });

    Schema::create('sessions', function (Blueprint $table) {
        $table->string('id')->primary();
        $table->foreignId('user_id')->nullable()->index();
        $table->string('ip_address', 45)->nullable();
        $table->text('user_agent')->nullable();
        $table->longText('payload');
        $table->integer('last_activity')->index();
    });
}
```

- [ ] **Step 2: Create follows migration**

```bash
cd /home/leonardo/intheend/instaclone-backend
php artisan make:migration create_follows_table
```

Edit the new migration file:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('follows', function (Blueprint $table) {
            $table->foreignId('follower_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('following_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->primary(['follower_id', 'following_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('follows');
    }
};
```

- [ ] **Step 3: Create posts migration**

```bash
cd /home/leonardo/intheend/instaclone-backend
php artisan make:migration create_posts_table
```

Edit the new migration file:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('image_path');
            $table->string('caption', 2200)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
```

- [ ] **Step 4: Create likes migration**

```bash
cd /home/leonardo/intheend/instaclone-backend
php artisan make:migration create_likes_table
```

Edit the new migration file:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('likes', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->primary(['user_id', 'post_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('likes');
    }
};
```

- [ ] **Step 5: Create comments migration**

```bash
cd /home/leonardo/intheend/instaclone-backend
php artisan make:migration create_comments_table
```

Edit the new migration file:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('body', 500);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
```

- [ ] **Step 6: Create notifications migration**

```bash
cd /home/leonardo/intheend/instaclone-backend
php artisan make:migration create_notifications_table
```

Edit the new migration file:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 50);
            $table->json('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
```

- [ ] **Step 7: Commit**

```bash
cd /home/leonardo/intheend/instaclone-backend
git add -A
git commit -m "feat: all database migrations"
```

---

## Task 4: Models

**Files:**
- Modify: `app/Models/User.php`
- Create: `app/Models/Post.php`
- Create: `app/Models/Like.php`
- Create: `app/Models/Comment.php`
- Create: `app/Models/Notification.php`

- [ ] **Step 1: Rewrite User model**

Replace `app/Models/User.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'bio',
        'avatar_path',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public function posts()
    {
        return $this->hasMany(Post::class)->latest();
    }

    public function likes()
    {
        return $this->hasMany(Like::class);
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class)->latest();
    }

    public function following()
    {
        return $this->belongsToMany(User::class, 'follows', 'follower_id', 'following_id')
                    ->withTimestamps();
    }

    public function followers()
    {
        return $this->belongsToMany(User::class, 'follows', 'following_id', 'follower_id')
                    ->withTimestamps();
    }

    public function isFollowing(User $user): bool
    {
        return $this->following()->where('following_id', $user->id)->exists();
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar_path
            ? asset('storage/' . $this->avatar_path)
            : null;
    }
}
```

- [ ] **Step 2: Create Post model**

Create `app/Models/Post.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'image_path', 'caption'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function likes()
    {
        return $this->hasMany(Like::class);
    }

    public function comments()
    {
        return $this->hasMany(Comment::class)->latest();
    }

    public function getImageUrlAttribute(): string
    {
        return asset('storage/' . $this->image_path);
    }

    public function isLikedBy(User $user): bool
    {
        return $this->likes()->where('user_id', $user->id)->exists();
    }
}
```

- [ ] **Step 3: Create Like model**

Create `app/Models/Like.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Like extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'post_id'];

    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function post()
    {
        return $this->belongsTo(Post::class);
    }
}
```

- [ ] **Step 4: Create Comment model**

Create `app/Models/Comment.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'post_id', 'body'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function post()
    {
        return $this->belongsTo(Post::class);
    }
}
```

- [ ] **Step 5: Create Notification model**

Create `app/Models/Notification.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    public $timestamps = false;

    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    protected $fillable = ['user_id', 'type', 'data', 'read_at'];

    protected function casts(): array
    {
        return [
            'data'    => 'array',
            'read_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
```

- [ ] **Step 6: Commit**

```bash
cd /home/leonardo/intheend/instaclone-backend
git add -A
git commit -m "feat: User, Post, Like, Comment, Notification models"
```

---

## Task 5: API Resources (Response Formatters)

**Files:**
- Create: `app/Http/Resources/UserResource.php`
- Create: `app/Http/Resources/PostResource.php`
- Create: `app/Http/Resources/CommentResource.php`
- Create: `app/Http/Resources/NotificationResource.php`

- [ ] **Step 1: Create UserResource**

Create `app/Http/Resources/UserResource.php`:
```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $authUser = $request->user();

        return [
            'id'              => $this->id,
            'name'            => $this->name,
            'username'        => $this->username,
            'bio'             => $this->bio,
            'avatar_url'      => $this->avatar_url,
            'posts_count'     => $this->whenCounted('posts'),
            'followers_count' => $this->whenCounted('followers'),
            'following_count' => $this->whenCounted('following'),
            'is_following'    => $this->when(
                $authUser && $authUser->id !== $this->id,
                fn () => $authUser->isFollowing($this->resource)
            ),
        ];
    }
}
```

- [ ] **Step 2: Create PostResource**

Create `app/Http/Resources/PostResource.php`:
```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $authUser = $request->user();

        return [
            'id'             => $this->id,
            'image_url'      => $this->image_url,
            'caption'        => $this->caption,
            'created_at'     => $this->created_at->toISOString(),
            'updated_at'     => $this->updated_at->toISOString(),
            'user'           => new UserResource($this->whenLoaded('user')),
            'likes_count'    => $this->whenCounted('likes'),
            'comments_count' => $this->whenCounted('comments'),
            'is_liked'       => $this->when(
                $authUser !== null,
                fn () => $this->isLikedBy($authUser)
            ),
        ];
    }
}
```

- [ ] **Step 3: Create CommentResource**

Create `app/Http/Resources/CommentResource.php`:
```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'body'       => $this->body,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            'user'       => new UserResource($this->whenLoaded('user')),
        ];
    }
}
```

- [ ] **Step 4: Create NotificationResource**

Create `app/Http/Resources/NotificationResource.php`:
```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'type'       => $this->type,
            'data'       => $this->data,
            'read_at'    => $this->read_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
```

- [ ] **Step 5: Commit**

```bash
cd /home/leonardo/intheend/instaclone-backend
git add -A
git commit -m "feat: API resources for User, Post, Comment, Notification"
```

---

## Task 6: Form Requests (Validation)

**Files:**
- Create: `app/Http/Requests/RegisterRequest.php`
- Create: `app/Http/Requests/LoginRequest.php`
- Create: `app/Http/Requests/UpdateProfileRequest.php`
- Create: `app/Http/Requests/StorePostRequest.php`
- Create: `app/Http/Requests/UpdatePostRequest.php`
- Create: `app/Http/Requests/StoreCommentRequest.php`

- [ ] **Step 1: RegisterRequest**

Create `app/Http/Requests/RegisterRequest.php`:
```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'max:30', 'alpha_dash', 'unique:users,username'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
```

- [ ] **Step 2: LoginRequest**

Create `app/Http/Requests/LoginRequest.php`:
```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ];
    }
}
```

- [ ] **Step 3: UpdateProfileRequest**

Create `app/Http/Requests/UpdateProfileRequest.php`:
```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'     => ['sometimes', 'string', 'max:100'],
            'username' => [
                'sometimes', 'string', 'max:30', 'alpha_dash',
                Rule::unique('users', 'username')->ignore($this->user()->id),
            ],
            'bio'      => ['sometimes', 'nullable', 'string', 'max:160'],
        ];
    }
}
```

- [ ] **Step 4: StorePostRequest**

Create `app/Http/Requests/StorePostRequest.php`:
```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'image'   => ['required', 'file', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
            'caption' => ['nullable', 'string', 'max:2200'],
        ];
    }
}
```

- [ ] **Step 5: UpdatePostRequest**

Create `app/Http/Requests/UpdatePostRequest.php`:
```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'caption' => ['present', 'nullable', 'string', 'max:2200'],
        ];
    }
}
```

- [ ] **Step 6: StoreCommentRequest**

Create `app/Http/Requests/StoreCommentRequest.php`:
```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:1', 'max:500'],
        ];
    }
}
```

- [ ] **Step 7: Commit**

```bash
cd /home/leonardo/intheend/instaclone-backend
git add -A
git commit -m "feat: form request validation classes"
```

---

## Task 7: Policies

**Files:**
- Create: `app/Policies/PostPolicy.php`
- Create: `app/Policies/CommentPolicy.php`

- [ ] **Step 1: PostPolicy**

Create `app/Policies/PostPolicy.php`:
```php
<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    public function modify(User $user, Post $post): bool
    {
        return $user->id === $post->user_id;
    }
}
```

- [ ] **Step 2: CommentPolicy**

Create `app/Policies/CommentPolicy.php`:
```php
<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\User;

class CommentPolicy
{
    public function modify(User $user, Comment $comment): bool
    {
        return $user->id === $comment->user_id;
    }
}
```

- [ ] **Step 3: Commit**

```bash
cd /home/leonardo/intheend/instaclone-backend
git add -A
git commit -m "feat: PostPolicy and CommentPolicy"
```

---

## Task 8: Services

**Files:**
- Create: `app/Services/AuthService.php`
- Create: `app/Services/UserService.php`
- Create: `app/Services/FollowService.php`
- Create: `app/Services/PostService.php`
- Create: `app/Services/FeedService.php`
- Create: `app/Services/LikeService.php`
- Create: `app/Services/CommentService.php`
- Create: `app/Services/NotificationService.php`

- [ ] **Step 1: AuthService**

Create `app/Services/AuthService.php`:
```php
<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function register(array $data): array
    {
        $user = User::create([
            'name'     => $data['name'],
            'username' => $data['username'],
            'email'    => $data['email'],
            'password' => $data['password'],
        ]);

        $token = $user->createToken('api')->plainTextToken;

        return compact('user', 'token');
    }

    public function login(string $email, string $password): array
    {
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw new AuthenticationException('Credenciais inválidas.');
        }

        $token = $user->createToken('api')->plainTextToken;

        return compact('user', 'token');
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }
}
```

- [ ] **Step 2: UserService**

Create `app/Services/UserService.php`:
```php
<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UserService
{
    public function updateProfile(User $user, array $data): User
    {
        $user->update($data);
        return $user->fresh();
    }

    public function updateAvatar(User $user, UploadedFile $file): User
    {
        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $path = $file->storeAs(
            'avatars',
            Str::uuid() . '.' . $file->getClientOriginalExtension(),
            'public'
        );

        $user->update(['avatar_path' => $path]);

        return $user->fresh();
    }

    public function findByUsername(string $username): User
    {
        return User::where('username', $username)
            ->withCount(['posts', 'followers', 'following'])
            ->firstOrFail();
    }

    public function search(string $query, int $perPage = 15): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return User::where('username', 'like', '%' . $query . '%')
            ->orWhere('name', 'like', '%' . $query . '%')
            ->withCount(['posts', 'followers', 'following'])
            ->orderBy('username')
            ->paginate($perPage);
    }
}
```

- [ ] **Step 3: FollowService**

Create `app/Services/FollowService.php`:
```php
<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;

class FollowService
{
    public function follow(User $follower, User $target): void
    {
        if ($follower->id === $target->id) {
            throw new HttpResponseException(
                response()->json(['message' => 'Você não pode seguir a si mesmo.'], 422)
            );
        }

        $follower->following()->syncWithoutDetaching([$target->id]);
    }

    public function unfollow(User $follower, User $target): void
    {
        $follower->following()->detach($target->id);
    }

    public function followers(User $user, int $perPage = 20): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return $user->followers()
            ->withCount(['posts', 'followers', 'following'])
            ->paginate($perPage);
    }

    public function following(User $user, int $perPage = 20): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return $user->following()
            ->withCount(['posts', 'followers', 'following'])
            ->paginate($perPage);
    }
}
```

- [ ] **Step 4: PostService**

Create `app/Services/PostService.php`:
```php
<?php

namespace App\Services;

use App\Models\Post;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PostService
{
    public function create(User $user, UploadedFile $image, ?string $caption): Post
    {
        $path = $image->storeAs(
            'posts',
            Str::uuid() . '.' . $image->getClientOriginalExtension(),
            'public'
        );

        return Post::create([
            'user_id'    => $user->id,
            'image_path' => $path,
            'caption'    => $caption,
        ]);
    }

    public function update(Post $post, ?string $caption): Post
    {
        $post->update(['caption' => $caption]);
        return $post->fresh();
    }

    public function delete(Post $post): void
    {
        Storage::disk('public')->delete($post->image_path);
        $post->delete();
    }

    public function byUser(User $user, int $perPage = 15): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return Post::where('user_id', $user->id)
            ->with('user')
            ->withCount(['likes', 'comments'])
            ->latest()
            ->paginate($perPage);
    }

    public function find(int $id): Post
    {
        return Post::with('user')
            ->withCount(['likes', 'comments'])
            ->findOrFail($id);
    }
}
```

- [ ] **Step 5: FeedService**

Create `app/Services/FeedService.php`:
```php
<?php

namespace App\Services;

use App\Models\Post;
use App\Models\User;

class FeedService
{
    public function feed(User $user, int $perPage = 15): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $followingIds = $user->following()->pluck('users.id');

        return Post::whereIn('user_id', $followingIds)
            ->with('user')
            ->withCount(['likes', 'comments'])
            ->latest()
            ->paginate($perPage);
    }
}
```

- [ ] **Step 6: LikeService**

Create `app/Services/LikeService.php`:
```php
<?php

namespace App\Services;

use App\Models\Like;
use App\Models\Post;
use App\Models\User;

class LikeService
{
    public function like(User $user, Post $post): bool
    {
        $created = false;

        Like::firstOrCreate(
            ['user_id' => $user->id, 'post_id' => $post->id],
            [],
        );

        return true;
    }

    public function unlike(User $user, Post $post): void
    {
        Like::where('user_id', $user->id)
            ->where('post_id', $post->id)
            ->delete();
    }

    public function likedBy(Post $post, int $perPage = 20): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return User::whereHas('likes', fn ($q) => $q->where('post_id', $post->id))
            ->withCount(['posts', 'followers', 'following'])
            ->paginate($perPage);
    }
}
```

- [ ] **Step 7: CommentService**

Create `app/Services/CommentService.php`:
```php
<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;

class CommentService
{
    public function create(User $user, Post $post, string $body): Comment
    {
        return Comment::create([
            'user_id' => $user->id,
            'post_id' => $post->id,
            'body'    => $body,
        ]);
    }

    public function update(Comment $comment, string $body): Comment
    {
        $comment->update(['body' => $body]);
        return $comment->fresh(['user']);
    }

    public function delete(Comment $comment): void
    {
        $comment->delete();
    }

    public function byPost(Post $post, int $perPage = 20): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return Comment::where('post_id', $post->id)
            ->with('user')
            ->latest()
            ->paginate($perPage);
    }
}
```

- [ ] **Step 8: NotificationService**

Create `app/Services/NotificationService.php`:
```php
<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Carbon;

class NotificationService
{
    public function notify(User $recipient, string $type, array $data): void
    {
        if ($recipient->id === $data['actor_id'] ?? null) {
            return;
        }

        Notification::create([
            'user_id' => $recipient->id,
            'type'    => $type,
            'data'    => $data,
        ]);
    }

    public function list(User $user, int $perPage = 20): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return Notification::where('user_id', $user->id)
            ->latest('created_at')
            ->paginate($perPage);
    }

    public function unreadCount(User $user): int
    {
        return Notification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();
    }

    public function markAllRead(User $user): void
    {
        Notification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => Carbon::now()]);
    }
}
```

- [ ] **Step 9: Commit**

```bash
cd /home/leonardo/intheend/instaclone-backend
git add -A
git commit -m "feat: all service classes"
```

---

## Task 9: Observers (Notification Side-Effects)

**Files:**
- Create: `app/Observers/LikeObserver.php`
- Create: `app/Observers/CommentObserver.php`
- Modify: `app/Providers/AppServiceProvider.php`

- [ ] **Step 1: LikeObserver**

Create `app/Observers/LikeObserver.php`:
```php
<?php

namespace App\Observers;

use App\Models\Like;
use App\Services\NotificationService;

class LikeObserver
{
    public function __construct(private NotificationService $notifications) {}

    public function created(Like $like): void
    {
        $post = $like->post()->with('user')->first();

        if ($post && $post->user_id !== $like->user_id) {
            $this->notifications->notify($post->user, 'like', [
                'actor_id'       => $like->user_id,
                'actor_username' => $like->user->username ?? null,
                'post_id'        => $post->id,
            ]);
        }
    }
}
```

- [ ] **Step 2: CommentObserver**

Create `app/Observers/CommentObserver.php`:
```php
<?php

namespace App\Observers;

use App\Models\Comment;
use App\Services\NotificationService;

class CommentObserver
{
    public function __construct(private NotificationService $notifications) {}

    public function created(Comment $comment): void
    {
        $post = $comment->post()->with('user')->first();

        if ($post && $post->user_id !== $comment->user_id) {
            $this->notifications->notify($post->user, 'comment', [
                'actor_id'       => $comment->user_id,
                'actor_username' => $comment->user->username ?? null,
                'post_id'        => $post->id,
                'comment_id'     => $comment->id,
            ]);
        }
    }
}
```

- [ ] **Step 3: Register observers in AppServiceProvider**

Replace `app/Providers/AppServiceProvider.php`:
```php
<?php

namespace App\Providers;

use App\Models\Comment;
use App\Models\Like;
use App\Models\User;
use App\Observers\CommentObserver;
use App\Observers\LikeObserver;
use App\Services\NotificationService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Like::observe(LikeObserver::class);
        Comment::observe(CommentObserver::class);

        // Follow notification wired via FollowService directly
    }
}
```

- [ ] **Step 4: Load user relation in observers (needed for username)**

Update `LikeObserver::created` to eager-load user:
```php
public function created(Like $like): void
{
    $like->loadMissing('user');
    $post = $like->post()->with('user')->first();

    if ($post && $post->user_id !== $like->user_id) {
        $this->notifications->notify($post->user, 'like', [
            'actor_id'       => $like->user_id,
            'actor_username' => $like->user->username,
            'post_id'        => $post->id,
        ]);
    }
}
```

Update `CommentObserver::created` to eager-load user:
```php
public function created(Comment $comment): void
{
    $comment->loadMissing('user');
    $post = $comment->post()->with('user')->first();

    if ($post && $post->user_id !== $comment->user_id) {
        $this->notifications->notify($post->user, 'comment', [
            'actor_id'       => $comment->user_id,
            'actor_username' => $comment->user->username,
            'post_id'        => $post->id,
            'comment_id'     => $comment->id,
        ]);
    }
}
```

- [ ] **Step 5: Commit**

```bash
cd /home/leonardo/intheend/instaclone-backend
git add -A
git commit -m "feat: LikeObserver and CommentObserver for notifications"
```

---

## Task 10: Controllers

**Files:**
- Create: `app/Http/Controllers/AuthController.php`
- Create: `app/Http/Controllers/UserController.php`
- Create: `app/Http/Controllers/PostController.php`
- Create: `app/Http/Controllers/FeedController.php`
- Create: `app/Http/Controllers/LikeController.php`
- Create: `app/Http/Controllers/CommentController.php`
- Create: `app/Http/Controllers/NotificationController.php`

- [ ] **Step 1: AuthController**

Create `app/Http/Controllers/AuthController.php`:
```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private AuthService $auth) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        ['user' => $user, 'token' => $token] = $this->auth->register($request->validated());

        return response()->json([
            'user'  => new UserResource($user),
            'token' => $token,
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        ['user' => $user, 'token' => $token] = $this->auth->login(
            $request->email,
            $request->password
        );

        return response()->json([
            'user'  => new UserResource($user),
            'token' => $token,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->auth->logout($request->user());

        return response()->json(['message' => 'Logout realizado com sucesso.']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->loadCount(['posts', 'followers', 'following']);

        return response()->json(new UserResource($user));
    }
}
```

- [ ] **Step 2: UserController**

Create `app/Http/Controllers/UserController.php`:
```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\PostResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\FollowService;
use App\Services\NotificationService;
use App\Services\PostService;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        private UserService $users,
        private PostService $posts,
        private FollowService $follows,
        private NotificationService $notifications,
    ) {}

    public function show(string $username): JsonResponse
    {
        $user = $this->users->findByUsername($username);
        return response()->json(new UserResource($user));
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $this->users->updateProfile($request->user(), $request->validated());
        $user->loadCount(['posts', 'followers', 'following']);
        return response()->json(new UserResource($user));
    }

    public function avatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => ['required', 'file', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ]);

        $user = $this->users->updateAvatar($request->user(), $request->file('avatar'));
        $user->loadCount(['posts', 'followers', 'following']);
        return response()->json(new UserResource($user));
    }

    public function search(Request $request): JsonResponse
    {
        $request->validate(['q' => ['required', 'string', 'min:1', 'max:50']]);

        $users = $this->users->search($request->q, $request->integer('per_page', 15));
        return response()->json(UserResource::collection($users)->response()->getData(true));
    }

    public function posts(User $user): JsonResponse
    {
        $posts = $this->posts->byUser($user, 15);
        return response()->json(PostResource::collection($posts)->response()->getData(true));
    }

    public function follow(Request $request, User $user): JsonResponse
    {
        $this->follows->follow($request->user(), $user);

        $this->notifications->notify($user, 'follow', [
            'actor_id'       => $request->user()->id,
            'actor_username' => $request->user()->username,
        ]);

        return response()->json(['message' => 'Seguindo.']);
    }

    public function unfollow(Request $request, User $user): JsonResponse
    {
        $this->follows->unfollow($request->user(), $user);
        return response()->json(['message' => 'Deixou de seguir.']);
    }

    public function followers(User $user): JsonResponse
    {
        $followers = $this->follows->followers($user, 20);
        return response()->json(UserResource::collection($followers)->response()->getData(true));
    }

    public function following(User $user): JsonResponse
    {
        $following = $this->follows->following($user, 20);
        return response()->json(UserResource::collection($following)->response()->getData(true));
    }

    public function isFollowing(Request $request, User $user): JsonResponse
    {
        return response()->json([
            'is_following' => $request->user()->isFollowing($user),
        ]);
    }
}
```

- [ ] **Step 3: PostController**

Create `app/Http/Controllers/PostController.php`:
```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Services\PostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function __construct(private PostService $posts) {}

    public function store(StorePostRequest $request): JsonResponse
    {
        $post = $this->posts->create(
            $request->user(),
            $request->file('image'),
            $request->caption
        );

        $post->load('user');
        $post->loadCount(['likes', 'comments']);

        return response()->json(new PostResource($post), 201);
    }

    public function show(Post $post): JsonResponse
    {
        $post = $this->posts->find($post->id);
        return response()->json(new PostResource($post));
    }

    public function update(UpdatePostRequest $request, Post $post): JsonResponse
    {
        $this->authorize('modify', $post);

        $post = $this->posts->update($post, $request->caption);
        $post->load('user');
        $post->loadCount(['likes', 'comments']);

        return response()->json(new PostResource($post));
    }

    public function destroy(Request $request, Post $post): JsonResponse
    {
        $this->authorize('modify', $post);
        $this->posts->delete($post);
        return response()->json(['message' => 'Post removido.']);
    }
}
```

- [ ] **Step 4: FeedController**

Create `app/Http/Controllers/FeedController.php`:
```php
<?php

namespace App\Http\Controllers;

use App\Http\Resources\PostResource;
use App\Services\FeedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeedController extends Controller
{
    public function __construct(private FeedService $feed) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = $request->integer('per_page', 15);
        $posts   = $this->feed->feed($request->user(), $perPage);

        return response()->json(PostResource::collection($posts)->response()->getData(true));
    }
}
```

- [ ] **Step 5: LikeController**

Create `app/Http/Controllers/LikeController.php`:
```php
<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\Post;
use App\Services\LikeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LikeController extends Controller
{
    public function __construct(private LikeService $likes) {}

    public function store(Request $request, Post $post): JsonResponse
    {
        $this->likes->like($request->user(), $post);
        return response()->json(['message' => 'Post curtido.']);
    }

    public function destroy(Request $request, Post $post): JsonResponse
    {
        $this->likes->unlike($request->user(), $post);
        return response()->json(['message' => 'Curtida removida.']);
    }

    public function index(Post $post): JsonResponse
    {
        $users = $this->likes->likedBy($post, 20);
        return response()->json(UserResource::collection($users)->response()->getData(true));
    }
}
```

- [ ] **Step 6: CommentController**

Create `app/Http/Controllers/CommentController.php`:
```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\Post;
use App\Services\CommentService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function __construct(private CommentService $comments) {}

    public function store(StoreCommentRequest $request, Post $post): JsonResponse
    {
        $comment = $this->comments->create($request->user(), $post, $request->body);
        $comment->load('user');

        return response()->json(new CommentResource($comment), 201);
    }

    public function index(Post $post): JsonResponse
    {
        $comments = $this->comments->byPost($post, 20);
        return response()->json(CommentResource::collection($comments)->response()->getData(true));
    }

    public function update(Request $request, Comment $comment): JsonResponse
    {
        $this->authorize('modify', $comment);

        $request->validate(['body' => ['required', 'string', 'min:1', 'max:500']]);

        $comment = $this->comments->update($comment, $request->body);
        return response()->json(new CommentResource($comment));
    }

    public function destroy(Request $request, Comment $comment): JsonResponse
    {
        $this->authorize('modify', $comment);
        $this->comments->delete($comment);
        return response()->json(['message' => 'Comentário removido.']);
    }
}
```

- [ ] **Step 7: NotificationController**

Create `app/Http/Controllers/NotificationController.php`:
```php
<?php

namespace App\Http\Controllers;

use App\Http\Resources\NotificationResource;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(private NotificationService $notifications) {}

    public function index(Request $request): JsonResponse
    {
        $perPage       = $request->integer('per_page', 20);
        $notifications = $this->notifications->list($request->user(), $perPage);

        return response()->json(NotificationResource::collection($notifications)->response()->getData(true));
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'count' => $this->notifications->unreadCount($request->user()),
        ]);
    }

    public function markRead(Request $request): JsonResponse
    {
        $this->notifications->markAllRead($request->user());
        return response()->json(['message' => 'Notificações marcadas como lidas.']);
    }
}
```

- [ ] **Step 8: Commit**

```bash
cd /home/leonardo/intheend/instaclone-backend
git add -A
git commit -m "feat: all controllers"
```

---

## Task 11: Routes

**Files:**
- Modify: `routes/api.php`

- [ ] **Step 1: Write all API routes**

Replace `routes/api.php`:
```php
<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\LikeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Auth
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('login',    [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me',      [AuthController::class, 'me']);
    });
});

// Protected routes
Route::middleware('auth:sanctum')->group(function () {

    // Feed
    Route::get('feed', [FeedController::class, 'index']);

    // Notifications
    Route::get('notifications',               [NotificationController::class, 'index']);
    Route::get('notifications/unread-count',  [NotificationController::class, 'unreadCount']);
    Route::put('notifications/read',          [NotificationController::class, 'markRead']);

    // Users
    Route::get('users/search',                [UserController::class, 'search']);
    Route::get('users/me',                    [UserController::class, 'showMe'])->missing(
        fn () => response()->json(['message' => 'Não autenticado.'], 401)
    );
    Route::put('users/me',                    [UserController::class, 'update']);
    Route::post('users/me/avatar',            [UserController::class, 'avatar']);
    Route::get('users/{user}',                [UserController::class, 'show']);
    Route::get('users/{user}/posts',          [UserController::class, 'posts']);
    Route::post('users/{user}/follow',        [UserController::class, 'follow']);
    Route::delete('users/{user}/unfollow',    [UserController::class, 'unfollow']);
    Route::get('users/{user}/followers',      [UserController::class, 'followers']);
    Route::get('users/{user}/following',      [UserController::class, 'following']);
    Route::get('users/{user}/is-following',   [UserController::class, 'isFollowing']);

    // Posts
    Route::post('posts',                      [PostController::class, 'store']);
    Route::get('posts/{post}',                [PostController::class, 'show']);
    Route::put('posts/{post}',                [PostController::class, 'update']);
    Route::delete('posts/{post}',             [PostController::class, 'destroy']);
    Route::post('posts/{post}/like',          [LikeController::class, 'store']);
    Route::delete('posts/{post}/unlike',      [LikeController::class, 'destroy']);
    Route::get('posts/{post}/likes',          [LikeController::class, 'index']);
    Route::post('posts/{post}/comments',      [CommentController::class, 'store']);
    Route::get('posts/{post}/comments',       [CommentController::class, 'index']);

    // Comments
    Route::put('comments/{comment}',          [CommentController::class, 'update']);
    Route::delete('comments/{comment}',       [CommentController::class, 'destroy']);
});
```

- [ ] **Step 2: Add showMe to UserController**

Add this method to `app/Http/Controllers/UserController.php` after `show()`:
```php
public function showMe(Request $request): JsonResponse
{
    $user = $request->user()->loadCount(['posts', 'followers', 'following']);
    return response()->json(new UserResource($user));
}
```

- [ ] **Step 3: Fix users/{user} to bind by username or id**

The frontend calls `GET /api/users/{username}` (string), so the `show()` route needs to resolve by username. Update `show()` in `UserController` to accept a string and look up by username:

The `users/{user}` routes that use `User $user` (model binding by id) need a route model binding by id for follow/unfollow/followers/following/is-following/posts. The `show()` uses username lookup. Update routes to separate them:

Replace the users section in `routes/api.php`:
```php
    // Users (profile lookup by username)
    Route::get('users/search',                [UserController::class, 'search']);
    Route::put('users/me',                    [UserController::class, 'update']);
    Route::post('users/me/avatar',            [UserController::class, 'avatar']);
    Route::get('users/me',                    [UserController::class, 'showMe']);

    // Users by username (must come after static segments)
    Route::get('users/{username}',            [UserController::class, 'show'])
        ->where('username', '[a-zA-Z0-9._-]+');

    // Users by numeric id
    Route::get('users/{user}/posts',          [UserController::class, 'posts']);
    Route::post('users/{user}/follow',        [UserController::class, 'follow']);
    Route::delete('users/{user}/unfollow',    [UserController::class, 'unfollow']);
    Route::get('users/{user}/followers',      [UserController::class, 'followers']);
    Route::get('users/{user}/following',      [UserController::class, 'following']);
    Route::get('users/{user}/is-following',   [UserController::class, 'isFollowing']);
```

Update `UserController::show()` signature:
```php
public function show(string $username): JsonResponse
{
    $user = $this->users->findByUsername($username);
    return response()->json(new UserResource($user));
}
```

- [ ] **Step 4: Commit**

```bash
cd /home/leonardo/intheend/instaclone-backend
git add -A
git commit -m "feat: API routes"
```

---

## Task 12: Seeders

**Files:**
- Modify: `database/seeders/DatabaseSeeder.php`

- [ ] **Step 1: Write DatabaseSeeder**

Replace `database/seeders/DatabaseSeeder.php`:
```php
<?php

namespace Database\Seeders;

use App\Models\Comment;
use App\Models\Like;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create demo users
        $alice = User::firstOrCreate(
            ['email' => 'alice@example.com'],
            [
                'name'     => 'Alice',
                'username' => 'alice',
                'password' => Hash::make('password'),
                'bio'      => 'Fotógrafa e viajante 📷',
            ]
        );

        $bob = User::firstOrCreate(
            ['email' => 'bob@example.com'],
            [
                'name'     => 'Bob',
                'username' => 'bob',
                'password' => Hash::make('password'),
                'bio'      => 'Desenvolvedor e gamer 🎮',
            ]
        );

        $carol = User::firstOrCreate(
            ['email' => 'carol@example.com'],
            [
                'name'     => 'Carol',
                'username' => 'carol',
                'password' => Hash::make('password'),
                'bio'      => 'Designer de UI/UX ✨',
            ]
        );

        // Follows
        $alice->following()->syncWithoutDetaching([$bob->id, $carol->id]);
        $bob->following()->syncWithoutDetaching([$alice->id]);
        $carol->following()->syncWithoutDetaching([$alice->id, $bob->id]);

        // Posts (using a placeholder public image path)
        $this->createPost($alice, 'Dia lindo hoje! ☀️');
        $this->createPost($bob, 'Setup novo 🖥️');
        $this->createPost($carol, 'Novo projeto em andamento 🎨');

        $this->command->info('Seeder concluído. Usuários: alice/bob/carol com senha: password');
    }

    private function createPost(User $user, string $caption): Post
    {
        // Store a minimal placeholder image for demo
        $filename = Str::uuid() . '.png';
        Storage::disk('public')->put(
            'posts/' . $filename,
            base64_decode(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
            )
        );

        $post = Post::create([
            'user_id'    => $user->id,
            'image_path' => 'posts/' . $filename,
            'caption'    => $caption,
        ]);

        // Add a like from another user
        Like::firstOrCreate(['user_id' => 1, 'post_id' => $post->id]);

        return $post;
    }
}
```

- [ ] **Step 2: Commit**

```bash
cd /home/leonardo/intheend/instaclone-backend
git add -A
git commit -m "feat: database seeder with demo users"
```

---

## Task 13: Docker Setup

**Files:**
- Create: `Dockerfile`
- Create: `compose.yaml`
- Create: `docker/entrypoint.sh`
- Create: `docker/php.ini`
- Create: `.dockerignore`

- [ ] **Step 1: Dockerfile**

Create `Dockerfile`:
```dockerfile
ARG PHP_VERSION=8.3
ARG COMPOSER_VERSION=2

# -----------------------------------------------------------------------------
# Stage 1: Install PHP dependencies
# -----------------------------------------------------------------------------
FROM composer:${COMPOSER_VERSION} AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

RUN --mount=type=cache,target=/tmp/composer-cache \
    COMPOSER_CACHE_DIR=/tmp/composer-cache \
    composer install \
        --no-dev \
        --no-interaction \
        --no-scripts \
        --prefer-dist \
        --no-progress

COPY . .

RUN composer dump-autoload --classmap-authoritative --no-dev

# -----------------------------------------------------------------------------
# Stage 2: FrankenPHP runtime
# -----------------------------------------------------------------------------
FROM dunglas/frankenphp:1-php${PHP_VERSION}-alpine AS runtime

ENV APP_ENV=production \
    APP_DEBUG=false \
    SERVER_NAME=":8000" \
    PHP_INI_MEMORY_LIMIT=256M \
    COMPOSER_ALLOW_SUPERUSER=1

RUN apk add --no-cache \
        bash \
        mysql-client \
        tini \
 && install-php-extensions \
        pdo_mysql \
        intl \
        zip \
        bcmath \
        opcache \
        pcntl \
        gd \
        redis

WORKDIR /app

COPY --from=vendor /app/vendor ./vendor
COPY --from=vendor /app        ./

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
COPY docker/php.ini       /usr/local/etc/php/conf.d/zz-app.ini

RUN chmod +x /usr/local/bin/entrypoint \
 && mkdir -p storage/framework/{cache,sessions,testing,views} storage/logs bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache \
 && chmod -R ug+rwX storage bootstrap/cache

EXPOSE 8000

HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=5 \
    CMD wget -qO- http://127.0.0.1:8000/up >/dev/null 2>&1 || exit 1

ENTRYPOINT ["/sbin/tini", "--", "/usr/local/bin/entrypoint"]

CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
```

- [ ] **Step 2: compose.yaml**

Create `compose.yaml`:
```yaml
services:
  mysql:
    container_name: instaclone_mysql
    image: mysql:8.4
    environment:
      MYSQL_ROOT_PASSWORD: rootpassword
      MYSQL_DATABASE: laravel
      MYSQL_USER: laravel
      MYSQL_PASSWORD: laravel
    volumes:
      - mysql_data:/var/lib/mysql
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost", "-u", "laravel", "-plaravel"]
      interval: 10s
      timeout: 5s
      retries: 10

  app:
    build: .
    image: instaclone/backend
    env_file:
      - .env
    environment:
      DB_HOST: mysql
      DB_PORT: 3306
      DB_DATABASE: laravel
      DB_USERNAME: laravel
      DB_PASSWORD: laravel
      RUN_MIGRATIONS: "true"
    ports:
      - "8000:8000"
    volumes:
      - app_storage:/app/storage
    depends_on:
      mysql:
        condition: service_healthy

volumes:
  mysql_data: {}
  app_storage: {}
```

- [ ] **Step 3: docker/entrypoint.sh**

Create `docker/entrypoint.sh`:
```bash
#!/usr/bin/env bash
set -euo pipefail

cd /app

if [ ! -f .env ]; then
    if [ -f .env.example ]; then
        cp .env.example .env
    else
        touch .env
    fi
fi

if [ -z "${APP_KEY:-}" ] && ! grep -q '^APP_KEY=.\+' .env 2>/dev/null; then
    php artisan key:generate --force
fi

if [ "${DB_CONNECTION:-}" = "mysql" ] && [ -n "${DB_HOST:-}" ]; then
    echo "Waiting for MySQL at ${DB_HOST}:${DB_PORT:-3306}..."
    for i in $(seq 1 60); do
        if mysqladmin ping -h "${DB_HOST}" -P "${DB_PORT:-3306}" \
            -u "${DB_USERNAME:-root}" -p"${DB_PASSWORD:-}" --silent >/dev/null 2>&1; then
            echo "MySQL ready."
            break
        fi
        if [ "$i" -eq 60 ]; then
            echo "MySQL did not become ready in time." >&2
            exit 1
        fi
        sleep 1
    done
fi

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    php artisan migrate --force --no-interaction
fi

php artisan storage:link --force 2>/dev/null || true

if [ "${APP_ENV:-production}" = "production" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan event:cache
else
    php artisan config:clear
    php artisan route:clear
fi

exec "$@"
```

- [ ] **Step 4: docker/php.ini**

Create `docker/php.ini`:
```ini
memory_limit = ${PHP_INI_MEMORY_LIMIT}
upload_max_filesize = 64M
post_max_size = 64M
max_execution_time = 120
date.timezone = UTC

opcache.enable = 1
opcache.enable_cli = 0
opcache.memory_consumption = 192
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 0
opcache.jit_buffer_size = 64M
opcache.jit = 1255
```

- [ ] **Step 5: .dockerignore**

Create `.dockerignore`:
```
.git
.github
node_modules
vendor
storage/logs/*
storage/framework/cache/*
storage/framework/sessions/*
storage/framework/views/*
bootstrap/cache/*
.env
.env.local
*.log
```

- [ ] **Step 6: Commit**

```bash
cd /home/leonardo/intheend/instaclone-backend
git add -A
git commit -m "feat: Docker multi-stage Dockerfile, compose.yaml, entrypoint, php.ini"
```

---

## Task 14: Run Migrations & Verify

- [ ] **Step 1: Start MySQL via Docker for local dev**

```bash
cd /home/leonardo/intheend/instaclone-backend
docker run -d \
  --name instaclone_dev_mysql \
  -e MYSQL_ROOT_PASSWORD=secret \
  -e MYSQL_DATABASE=instaclone \
  -p 3306:3306 \
  mysql:8.4
```

- [ ] **Step 2: Wait for MySQL to be ready, run migrations**

```bash
sleep 15
cd /home/leonardo/intheend/instaclone-backend
php artisan migrate --force
```

Expected output: migration table created + all 6 migrations run successfully.

- [ ] **Step 3: Run storage:link**

```bash
cd /home/leonardo/intheend/instaclone-backend
php artisan storage:link
```

- [ ] **Step 4: Run seeder**

```bash
cd /home/leonardo/intheend/instaclone-backend
php artisan db:seed
```

Expected: "Seeder concluído. Usuários: alice/bob/carol com senha: password"

- [ ] **Step 5: Smoke-test the API**

```bash
# Register
curl -s -X POST http://localhost:8000/api/auth/register \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json' \
  -d '{"name":"Test","username":"testuser","email":"test@test.com","password":"password123","password_confirmation":"password123"}' | jq .

# Login
curl -s -X POST http://localhost:8000/api/auth/login \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json' \
  -d '{"email":"alice@example.com","password":"password"}' | jq .
```

Expected: 201 with user + token on register, 200 with user + token on login.

- [ ] **Step 6: Final commit**

```bash
cd /home/leonardo/intheend/instaclone-backend
git add -A
git commit -m "chore: verified migrations, seeder, and smoke test"
```

---

## Task 15: Build & Test Docker Stack

- [ ] **Step 1: Stop dev MySQL container**

```bash
docker stop instaclone_dev_mysql && docker rm instaclone_dev_mysql
```

- [ ] **Step 2: Build and start full Docker stack**

```bash
cd /home/leonardo/intheend/instaclone-backend
docker compose up -d --build
```

Expected: both `mysql` and `app` containers start, migrations run automatically.

- [ ] **Step 3: Check health**

```bash
docker compose ps
curl -s http://localhost:8000/up
```

Expected: `{"status":"ok"}` or similar Laravel health check response.

- [ ] **Step 4: Smoke-test against Docker stack**

```bash
curl -s -X POST http://localhost:8000/api/auth/login \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json' \
  -d '{"email":"alice@example.com","password":"password"}' | jq .
```

- [ ] **Step 5: Final tag commit**

```bash
cd /home/leonardo/intheend/instaclone-backend
git add -A
git commit -m "chore: Docker stack verified working end-to-end"
```
