<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\FollowController;
use App\Http\Controllers\LikeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SaveController;
use App\Http\Controllers\ExploreController;
use App\Http\Controllers\RepostController;
use App\Http\Controllers\StoryController;
use Illuminate\Support\Facades\Route;

// ── Auth ──────────────────────────────────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('login',    [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::post('logout',  [AuthController::class, 'logout']);
        Route::get('me',       [AuthController::class, 'me']);
    });
});

// ── Protected ─────────────────────────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'throttle:300,1'])->group(function () {

    // Stories
    Route::get('stories/feed',            [StoryController::class, 'feed']);
    Route::post('stories',                [StoryController::class, 'store']);
    Route::post('stories/{story}/seen',   [StoryController::class, 'markSeen']);
    Route::delete('stories/{story}',      [StoryController::class, 'destroy']);

    // Feed
    Route::get('feed', [FeedController::class, 'index']);

    // Notifications
    Route::get('notifications',              [NotificationController::class, 'index']);
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::put('notifications/read',         [NotificationController::class, 'markRead']);

    // Profile — static segments must come before wildcard routes
    Route::get('users/search',               [ProfileController::class, 'search']);
    Route::get('users/suggestions',          [ProfileController::class, 'suggestions']);
    Route::get('users/me',                   [ProfileController::class, 'showMe']);
    Route::put('users/me',                   [ProfileController::class, 'update']);
    Route::post('users/me/avatar',           [ProfileController::class, 'avatar']);
    Route::put('users/me/privacy',           [ProfileController::class, 'privacy']);
    Route::delete('users/me',                [ProfileController::class, 'destroyAccount']);
    Route::get('users/me/saved',             [SaveController::class, 'mySaved']);

    // Profile by username (lookup)
    Route::get('users/{username}', [ProfileController::class, 'show'])
        ->where('username', '[a-zA-Z0-9._-]+');

    // Profile posts & reposts
    Route::get('users/{user}/posts',   [ProfileController::class, 'posts']);
    Route::get('users/{user}/reposts', [RepostController::class, 'userReposts']);

    // Follow / social
    Route::post('users/{user}/follow',          [FollowController::class, 'follow']);
    Route::delete('users/{user}/follow',        [FollowController::class, 'unfollow']);
    Route::delete('users/{user}/followers',     [FollowController::class, 'removeFollower']);
    Route::post('users/{user}/follow/accept',   [FollowController::class, 'acceptRequest']);
    Route::post('users/{user}/follow/decline',  [FollowController::class, 'declineRequest']);
    Route::get('users/{user}/followers',        [FollowController::class, 'followers']);
    Route::get('users/{user}/following',        [FollowController::class, 'following']);
    Route::get('users/{user}/is-following',     [FollowController::class, 'isFollowing']);

    // Posts
    Route::get('posts/explore',       [ExploreController::class, 'index']);
    Route::post('posts',              [PostController::class, 'store']);
    Route::get('posts/{post}',        [PostController::class, 'show']);
    Route::put('posts/{post}',        [PostController::class, 'update']);
    Route::delete('posts/{post}',     [PostController::class, 'destroy']);
    Route::post('posts/{post}/like',   [LikeController::class, 'store']);
    Route::delete('posts/{post}/like', [LikeController::class, 'destroy']);
    Route::get('posts/{post}/likes',     [LikeController::class, 'index']);
    Route::post('posts/{post}/save',    [SaveController::class, 'store']);
    Route::delete('posts/{post}/save',  [SaveController::class, 'destroy']);
    Route::post('posts/{post}/repost',  [RepostController::class, 'store']);
    Route::delete('posts/{post}/repost',[RepostController::class, 'destroy']);
    Route::post('posts/{post}/comments', [CommentController::class, 'store']);
    Route::get('posts/{post}/comments',  [CommentController::class, 'index']);

    // Comments
    Route::put('comments/{comment}',    [CommentController::class, 'update']);
    Route::delete('comments/{comment}', [CommentController::class, 'destroy']);
});
