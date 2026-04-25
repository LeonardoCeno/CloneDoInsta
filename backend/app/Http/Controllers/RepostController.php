<?php

namespace App\Http\Controllers;

use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Models\Repost;
use App\Models\User;
use App\Services\PostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RepostController extends Controller
{
    public function __construct(private PostService $posts) {}

    public function store(Request $request, Post $post): JsonResponse
    {
        $user = $request->user();

        if ($post->user_id === $user->id) {
            return response()->json(['message' => 'Não é possível repostar o próprio post.'], 422);
        }

        Repost::firstOrCreate([
            'user_id' => $user->id,
            'post_id' => $post->id,
        ]);

        return response()->json(['reposted' => true]);
    }

    public function destroy(Request $request, Post $post): JsonResponse
    {
        Repost::where('user_id', $request->user()->id)
            ->where('post_id', $post->id)
            ->delete();

        return response()->json(['reposted' => false]);
    }

    public function userReposts(Request $request, User $user): JsonResponse
    {
        $viewer = $request->user();
        $perPage = $this->perPage($request, 15);
        $posts = $this->posts->repostsByUser($user, $perPage, $viewer);

        return response()->json(PostResource::collection($posts)->response()->getData(true));
    }
}
