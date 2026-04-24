<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\FollowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FollowController extends Controller
{
    public function __construct(private FollowService $follows) {}

    public function follow(Request $request, User $user): JsonResponse
    {
        $this->follows->follow($request->user(), $user);

        return response()->json(['message' => 'Seguindo.']);
    }

    public function unfollow(Request $request, User $user): JsonResponse
    {
        $this->follows->unfollow($request->user(), $user);

        return response()->json(['message' => 'Deixou de seguir.']);
    }

    public function followers(Request $request, User $user): JsonResponse
    {
        $followers = $this->follows->followers($user, $this->perPage($request), $request->user());

        return response()->json(UserResource::collection($followers)->response()->getData(true));
    }

    public function following(Request $request, User $user): JsonResponse
    {
        $following = $this->follows->following($user, $this->perPage($request), $request->user());

        return response()->json(UserResource::collection($following)->response()->getData(true));
    }

    public function isFollowing(Request $request, User $user): JsonResponse
    {
        return response()->json([
            'is_following' => $request->user()->isFollowing($user),
        ]);
    }
}
