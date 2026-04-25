<?php

namespace App\Services;

use App\Exceptions\SelfFollowException;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class FollowService
{
    public function __construct(private NotificationService $notifications) {}

    public function follow(User $follower, User $target): void
    {
        if ($follower->id === $target->id) {
            throw new SelfFollowException();
        }

        $follower->following()->syncWithoutDetaching([$target->id]);

        $this->notifications->notify($target, 'follow', [
            'actor_id'       => $follower->id,
            'actor_username' => $follower->username,
        ]);
    }

    public function unfollow(User $follower, User $target): void
    {
        $follower->following()->detach($target->id);
    }

    public function followers(User $user, int $perPage = 20, ?User $viewer = null): LengthAwarePaginator
    {
        return $user->followers()
            ->withUserCounts()
            ->withFollowedByViewer($viewer)
            ->paginate($perPage);
    }

    public function following(User $user, int $perPage = 20, ?User $viewer = null): LengthAwarePaginator
    {
        return $user->following()
            ->withUserCounts()
            ->withFollowedByViewer($viewer)
            ->paginate($perPage);
    }
}
