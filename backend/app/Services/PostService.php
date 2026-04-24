<?php

namespace App\Services;

use App\Models\Post;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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

        return $post->fresh()->load('user')->loadCount(['likes', 'comments']);
    }

    public function delete(Post $post): void
    {
        Storage::disk('public')->delete($post->image_path);
        $post->delete();
    }

    public function byUser(User $user, int $perPage = 15, ?User $viewer = null): LengthAwarePaginator
    {
        return Post::where('user_id', $user->id)
            ->with('user')
            ->withPostCounts()
            ->withLikedByViewer($viewer)
            ->latest()
            ->paginate($perPage);
    }

    public function find(int $id, ?User $viewer = null): Post
    {
        return Post::with('user')
            ->withPostCounts()
            ->withLikedByViewer($viewer)
            ->findOrFail($id);
    }
}
