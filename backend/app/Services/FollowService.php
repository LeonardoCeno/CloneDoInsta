<?php

namespace App\Services;

use App\Exceptions\SelfFollowException;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class FollowService
{
    public function __construct(private NotificationService $notifications) {}

    public function follow(User $follower, User $target): array
    {
        if ($follower->id === $target->id) {
            throw new SelfFollowException();
        }

        $status = $target->is_private ? 'pending' : 'accepted';

        // insertOrIgnore is atomic — prevents duplicate rows even with concurrent requests
        $affected = DB::table('follows')->insertOrIgnore([
            'follower_id'  => $follower->id,
            'following_id' => $target->id,
            'status'       => $status,
            'created_at'   => now(),
        ]);

        if (!$affected) {
            // Row already existed — return current status without sending another notification
            $currentStatus = DB::table('follows')
                ->where('follower_id', $follower->id)
                ->where('following_id', $target->id)
                ->value('status');
            return ['status' => $currentStatus ?? $status];
        }

        $notificationType = $target->is_private ? 'follow_request' : 'follow';
        $this->notifications->notify($target, $notificationType, [
            'actor_id'       => $follower->id,
            'actor_username' => $follower->username,
        ]);

        return ['status' => $status];
    }

    public function unfollow(User $follower, User $target): void
    {
        DB::table('follows')
            ->where('follower_id', $follower->id)
            ->where('following_id', $target->id)
            ->delete();

        $this->notifications->deleteFollowRequestFrom($target, $follower->id);
    }

    public function removeFollower(User $target, User $follower): void
    {
        DB::table('follows')
            ->where('follower_id', $follower->id)
            ->where('following_id', $target->id)
            ->delete();
    }

    public function acceptRequest(User $target, User $requester): void
    {
        DB::table('follows')
            ->where('follower_id', $requester->id)
            ->where('following_id', $target->id)
            ->where('status', 'pending')
            ->update(['status' => 'accepted']);

        $this->notifications->deleteFollowRequestFrom($target, $requester->id);
    }

    public function declineRequest(User $target, User $requester): void
    {
        DB::table('follows')
            ->where('follower_id', $requester->id)
            ->where('following_id', $target->id)
            ->where('status', 'pending')
            ->delete();

        $this->notifications->deleteFollowRequestFrom($target, $requester->id);
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
