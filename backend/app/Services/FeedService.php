<?php

namespace App\Services;

use App\Models\Post;
use App\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;

class FeedService
{
    public function feed(User $user, int $perPage = 15): CursorPaginator
    {
        $followingIds = $user->following()->pluck('users.id');

        return Post::whereIn('user_id', $followingIds)
            ->with('user')
            ->withPostCounts()
            ->withLikedByViewer($user)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate($perPage);
    }
}
