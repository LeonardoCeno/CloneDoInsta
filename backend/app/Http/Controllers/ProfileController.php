<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAvatarRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\PostResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\PostService;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfileController extends Controller
{
    public function __construct(
        private UserService $users,
        private PostService $posts,
    ) {}

    public function show(string $username): JsonResponse
    {
        $user = $this->users->findByUsername($username);

        return response()->json(new UserResource($user));
    }

    public function showMe(Request $request): JsonResponse
    {
        $user = $request->user()->loadCount(['posts', 'followers', 'following']);

        return response()->json(new UserResource($user));
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $this->users->updateProfile($request->user(), $request->validated());

        return response()->json(new UserResource($user));
    }

    public function avatar(UpdateAvatarRequest $request): JsonResponse
    {
        $user = $this->users->updateAvatar($request->user(), $request->file('avatar'));

        return response()->json(new UserResource($user));
    }

    public function suggestions(Request $request): JsonResponse
    {
        $users = $this->users->suggestions($request->user(), $this->perPage($request));

        return response()->json(UserResource::collection($users)->response()->getData(true));
    }

    public function search(Request $request): JsonResponse
    {
        $request->validate(['q' => ['required', 'string', 'min:1', 'max:50']]);

        $users = $this->users->search($request->string('q'), $this->perPage($request, 15), $request->user());

        return response()->json(UserResource::collection($users)->response()->getData(true));
    }

    public function posts(Request $request, User $user): JsonResponse
    {
        $posts = $this->posts->byUser($user, $this->perPage($request, 15), $request->user());

        return response()->json(PostResource::collection($posts)->response()->getData(true));
    }

    public function destroyAccount(Request $request): JsonResponse
    {
        $request->validate(['password' => 'required|string']);

        $user = $request->user();

        if (!\Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Senha incorreta.'], 422);
        }

        $user->tokens()->delete();
        $this->users->deleteAccount($user);

        return response()->json(['message' => 'Conta excluída.']);
    }

    public function privacy(Request $request): JsonResponse
    {
        $user       = $request->user();
        $wasPrivate = (bool) $user->is_private;

        DB::transaction(function () use ($user, $wasPrivate) {
            $user->update(['is_private' => !$wasPrivate]);

            if ($wasPrivate) {
                DB::table('follows')
                    ->where('following_id', $user->id)
                    ->where('status', 'pending')
                    ->update(['status' => 'accepted']);
            }
        });

        $user->loadCount(['posts', 'followers', 'following']);

        return response()->json(new UserResource($user));
    }
}
