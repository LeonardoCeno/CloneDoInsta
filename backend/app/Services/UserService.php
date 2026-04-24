<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UserService
{
    public function updateProfile(User $user, array $data): User
    {
        $user->update($data);

        return $user->fresh()->loadCount(['posts', 'followers', 'following']);
    }

    public function updateAvatar(User $user, UploadedFile $file): User
    {
        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $path = $file->storeAs(
            'avatars',
            Str::uuid() . '.' . $file->getClientOriginalExtension(),
            'public'
        );

        $user->update(['avatar_path' => $path]);

        return $user->fresh()->loadCount(['posts', 'followers', 'following']);
    }

    public function findByUsername(string $username): User
    {
        return User::where('username', $username)
            ->withUserCounts()
            ->firstOrFail();
    }

    public function search(string $query, int $perPage = 15, ?User $viewer = null): LengthAwarePaginator
    {
        $safe = '%' . addcslashes($query, '%_\\') . '%';

        return User::where('username', 'like', $safe)
            ->orWhere('name', 'like', $safe)
            ->withUserCounts()
            ->withFollowedByViewer($viewer)
            ->orderBy('username')
            ->paginate($perPage);
    }

    public function suggestions(User $viewer, int $perPage = 20): LengthAwarePaginator
    {
        $followingIds = $viewer->following()->pluck('users.id')->push($viewer->id);

        return User::whereNotIn('id', $followingIds)
            ->withUserCounts()
            ->withFollowedByViewer($viewer)
            ->orderByDesc('followers_count')
            ->paginate($perPage);
    }
}
