<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\HasPreloadedAttribute;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    use HasPreloadedAttribute;
    public function toArray(Request $request): array
    {
        $authUser = $request->user();

        return [
            'id'             => $this->id,
            'image_url'      => $this->image_url,
            'caption'        => $this->caption,
            'created_at'     => $this->created_at?->toISOString(),
            'updated_at'     => $this->updated_at?->toISOString(),
            'user'           => new UserResource($this->whenLoaded('user')),
            'likes_count'    => $this->whenCounted('likes'),
            'comments_count' => $this->whenCounted('comments'),
            'liked_by_me'    => $this->when(
                $authUser !== null,
                fn () => $this->preloadedBool('liked_by_me', fn () => $this->isLikedBy($authUser))
            ),
            'saved_by_me'    => $this->when(
                $authUser !== null,
                fn () => $this->preloadedBool('saved_by_me', fn () => $this->isSavedBy($authUser))
            ),
        ];
    }
}
