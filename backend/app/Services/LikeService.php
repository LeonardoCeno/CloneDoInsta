<?php

namespace App\Services;

use App\Models\Like;
use App\Models\Post;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class LikeService
{
    public function like(User $user, Post $post): void
    {
        Like::firstOrCreate([
            'user_id' => $user->id,
            'post_id' => $post->id,
        ]);
    }

    public function unlike(User $user, Post $post): void
    {
        Like::where('user_id', $user->id)
            ->where('post_id', $post->id)
            ->delete();
    }

    public function likedBy(Post $post, int $perPage = 20): LengthAwarePaginator
    {
        return User::whereHas('likes', fn ($q) => $q->where('post_id', $post->id))
            ->withCount(['posts', 'followers', 'following'])
            ->paginate($perPage);
    }
}
