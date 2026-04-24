<?php

namespace App\Observers;

use App\Models\Like;
use App\Services\NotificationService;

class LikeObserver
{
    public function __construct(private NotificationService $notifications) {}

    public function created(Like $like): void
    {
        $like->loadMissing('user');
        $post = $like->post()->with('user')->first();

        if (! $post || $post->user_id === $like->user_id) {
            return;
        }

        $this->notifications->notify($post->user, 'like', [
            'actor_id'       => $like->user_id,
            'actor_username' => $like->user->username,
            'post_id'        => $post->id,
        ]);
    }
}
