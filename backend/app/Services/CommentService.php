<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

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

        return $comment->fresh()->load('user');
    }

    public function delete(Comment $comment): void
    {
        $comment->delete();
    }

    public function byPost(Post $post, int $perPage = 20): LengthAwarePaginator
    {
        return Comment::where('post_id', $post->id)
            ->with('user')
            ->latest()
            ->paginate($perPage);
    }
}
