<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class NotificationService
{
    public function notify(User $recipient, string $type, array $data): void
    {
        if (isset($data['actor_id']) && $recipient->id === $data['actor_id']) {
            return;
        }

        if ($type === 'follow_request' && isset($data['actor_id'])) {
            $this->deleteFollowRequestFrom($recipient, $data['actor_id']);
        }

        Notification::create([
            'user_id' => $recipient->id,
            'type'    => $type,
            'data'    => $data,
        ]);
    }

    public function deleteFollowRequestFrom(User $recipient, int $actorId): void
    {
        Notification::where('user_id', $recipient->id)
            ->where('type', 'follow_request')
            ->where('data->actor_id', $actorId)
            ->delete();
    }

    public function list(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return Notification::where('user_id', $user->id)
            ->latest('created_at')
            ->paginate($perPage);
    }

    public function unreadCount(User $user): int
    {
        return Notification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();
    }

    public function markAllRead(User $user): void
    {
        Notification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => Carbon::now()]);
    }
}
