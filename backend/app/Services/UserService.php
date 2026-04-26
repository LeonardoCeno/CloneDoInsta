<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
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
            Storage::disk('gcs')->delete($user->avatar_path);
        }

        $path = $file->storeAs(
            'avatars',
            Str::uuid() . '.' . $file->getClientOriginalExtension(),
            'gcs'
        );

        $user->update(['avatar_path' => $path]);

        return $user->fresh()->loadCount(['posts', 'followers', 'following']);
    }

    public function deleteAccount(User $user): void
    {
        $imagePaths = $user->posts->pluck('image_path')->filter()->all();
        $avatarPath = $user->avatar_path;

        DB::transaction(function () use ($user) {
            $user->delete();
        });

        Storage::disk('gcs')->delete($imagePaths);

        if ($avatarPath) {
            Storage::disk('gcs')->delete($avatarPath);
        }
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
            ->withFollowPendingByViewer($viewer)
            ->orderBy('username')
            ->paginate($perPage);
    }

    public function suggestions(User $viewer, int $perPage = 20): LengthAwarePaginator
    {
        $acceptedIds = DB::table('follows')
            ->where('follower_id', $viewer->id)
            ->where('status', 'accepted')
            ->pluck('following_id')
            ->push($viewer->id);

        return User::whereNotIn('id', $acceptedIds)
            ->withUserCounts()
            ->withFollowedByViewer($viewer)
            ->withFollowPendingByViewer($viewer)
            ->orderByDesc('followers_count')
            ->paginate($perPage);
    }
}
