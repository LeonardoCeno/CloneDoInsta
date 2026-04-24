<?php

namespace App\Services;

use App\Models\Post;
use App\Models\Save;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SaveService
{
    public function save(User $user, Post $post): void
    {
        Save::firstOrCreate([
            'user_id' => $user->id,
            'post_id' => $post->id,
        ]);
    }

    public function unsave(User $user, Post $post): void
    {
        Save::where('user_id', $user->id)
            ->where('post_id', $post->id)
            ->delete();
    }

    public function savedByUser(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return Post::whereHas('saves', fn ($q) => $q->where('user_id', $user->id))
            ->with('user')
            ->withPostCounts()
            ->withLikedByViewer($user)
            ->withSavedByViewer($user)
            ->latest()
            ->paginate($perPage);
    }
}
