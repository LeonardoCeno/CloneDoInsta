<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\HasPreloadedAttribute;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    use HasPreloadedAttribute;
    public function toArray(Request $request): array
    {
        $authUser = $request->user();

        return [
            'id'               => $this->id,
            'name'             => $this->name,
            'username'         => $this->username,
            'email'            => $this->when($authUser?->id === $this->id, $this->email),
            'bio'              => $this->bio,
            'avatar_url'       => $this->avatar_url,
            'created_at'       => $this->created_at?->toISOString(),
            'is_private'       => (bool) $this->is_private,
            'posts_count'      => $this->whenCounted('posts'),
            'followers_count'  => $this->whenCounted('followers'),
            'following_count'  => $this->whenCounted('following'),
            'is_following'     => $this->when(
                $authUser && $authUser->id !== $this->id,
                fn () => $this->preloadedBool('is_followed_by_viewer', fn () => $authUser->isFollowing($this->resource))
            ),
            'is_follow_pending' => $this->when(
                $authUser && $authUser->id !== $this->id,
                fn () => $this->preloadedBool('is_follow_pending_by_viewer', fn () => $authUser->isFollowPending($this->resource))
            ),
        ];
    }
}
