<?php

namespace App\Services;

use App\Models\Post;
use App\Models\Repost;
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
            'gcs'
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
        Storage::disk('gcs')->delete($post->image_path);
        $post->delete();
    }

    public function byUser(User $user, int $perPage = 15, ?User $viewer = null): LengthAwarePaginator
    {
        if ($user->is_private && !$this->viewerCanSee($viewer, $user)) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage, 1);
        }

        return Post::where('user_id', $user->id)
            ->with('user')
            ->withPostCounts()
            ->withLikedByViewer($viewer)
            ->withSavedByViewer($viewer)
            ->withRepostedByViewer($viewer)
            ->latest()
            ->paginate($perPage);
    }

    public function explore(int $perPage = 18, ?User $viewer = null): LengthAwarePaginator
    {
        $query = Post::with('user')
            ->withPostCounts()
            ->withLikedByViewer($viewer)
            ->withSavedByViewer($viewer)
            ->withRepostedByViewer($viewer);

        if ($viewer) {
            $query->whereHas('user', fn ($q) =>
                $q->where('is_private', false)
                  ->orWhereHas('followers', fn ($f) => $f->where('follower_id', $viewer->id))
            );
        } else {
            $query->whereHas('user', fn ($q) => $q->where('is_private', false));
        }

        return $query->latest()->paginate($perPage);
    }

    private function viewerCanSee(?User $viewer, User $user): bool
    {
        if (!$viewer) return false;
        if ($viewer->id === $user->id) return true;

        return \Illuminate\Support\Facades\DB::table('follows')
            ->where('follower_id', $viewer->id)
            ->where('following_id', $user->id)
            ->where('status', 'accepted')
            ->exists();
    }

    public function find(int $id, ?User $viewer = null): Post
    {
        return Post::with('user')
            ->withPostCounts()
            ->withLikedByViewer($viewer)
            ->withSavedByViewer($viewer)
            ->withRepostedByViewer($viewer)
            ->findOrFail($id);
    }

    public function repostsByUser(User $user, int $perPage = 15, ?User $viewer = null): LengthAwarePaginator
    {
        $repostIds = Repost::where('user_id', $user->id)
            ->latest('created_at')
            ->pluck('post_id');

        if ($repostIds->isEmpty()) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage, 1);
        }

        $placeholders = implode(',', array_fill(0, $repostIds->count(), '?'));

        return Post::whereIn('id', $repostIds)
            ->with('user')
            ->withPostCounts()
            ->withLikedByViewer($viewer)
            ->withSavedByViewer($viewer)
            ->withRepostedByViewer($viewer)
            ->orderByRaw("FIELD(id, {$placeholders})", $repostIds->all())
            ->paginate($perPage);
    }
}
