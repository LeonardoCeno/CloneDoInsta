<?php

namespace App\Services;

use App\Models\Post;
use App\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Support\Facades\DB;

class FeedService
{
    public function feed(User $user, int $perPage = 15): CursorPaginator
    {
        $followingIds = DB::table('follows')
            ->where('follower_id', $user->id)
            ->where('status', 'accepted')
            ->pluck('following_id');

        return Post::whereIn('user_id', $followingIds)
            ->with('user')
            ->withPostCounts()
            ->withLikedByViewer($user)
            ->withSavedByViewer($user)
            ->withRepostedByViewer($user)
            ->inRandomOrder()
            ->cursorPaginate($perPage);
    }
}
