<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\FollowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FollowController extends Controller
{
    public function __construct(private FollowService $follows) {}

    public function follow(Request $request, User $user): JsonResponse
    {
        $result = $this->follows->follow($request->user(), $user);

        $message = $result['status'] === 'pending' ? 'Solicitação enviada.' : 'Seguindo.';

        return response()->json(['message' => $message, 'status' => $result['status']]);
    }

    public function unfollow(Request $request, User $user): JsonResponse
    {
        $this->follows->unfollow($request->user(), $user);

        return response()->json(['message' => 'Deixou de seguir.']);
    }

    public function removeFollower(Request $request, User $user): JsonResponse
    {
        $this->follows->removeFollower($request->user(), $user);

        return response()->json(['message' => 'Seguidor removido.']);
    }

    public function acceptRequest(Request $request, User $user): JsonResponse
    {
        $this->follows->acceptRequest($request->user(), $user);

        return response()->json(['message' => 'Solicitação aceita.']);
    }

    public function declineRequest(Request $request, User $user): JsonResponse
    {
        $this->follows->declineRequest($request->user(), $user);

        return response()->json(['message' => 'Solicitação recusada.']);
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
        $status = DB::table('follows')
            ->where('follower_id', $request->user()->id)
            ->where('following_id', $user->id)
            ->value('status');

        return response()->json([
            'is_following' => $status === 'accepted',
            'is_pending'   => $status === 'pending',
        ]);
    }
}
