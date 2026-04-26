<?php

namespace App\Observers;

use App\Models\Comment;
use App\Services\NotificationService;

class CommentObserver
{
    public function __construct(private NotificationService $notifications) {}

    public function created(Comment $comment): void
    {
        $comment->loadMissing('user');
        $post = $comment->post()->with('user')->first();

        if (! $post || $post->user_id === $comment->user_id) {
            return;
        }

        $this->notifications->notify($post->user, 'comment', [
            'actor_id'         => $comment->user_id,
            'actor_username'   => $comment->user->username,
            'actor_avatar_url' => $comment->user->avatar_url,
            'post_id'          => $post->id,
            'post_image_url'   => $post->image_url,
            'post_is_video'    => $post->is_video,
            'comment_id'       => $comment->id,
        ]);
    }
}
