<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\Post;
use App\Services\LikeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LikeController extends Controller
{
    public function __construct(private LikeService $likes) {}

    public function store(Request $request, Post $post): JsonResponse
    {
        $this->likes->like($request->user(), $post);
        $post->loadCount('likes');

        return response()->json([
            'liked'       => true,
            'likes_count' => $post->likes_count,
        ]);
    }

    public function destroy(Request $request, Post $post): JsonResponse
    {
        $this->likes->unlike($request->user(), $post);
        $post->loadCount('likes');

        return response()->json([
            'liked'       => false,
            'likes_count' => $post->likes_count,
        ]);
    }

    public function index(Post $post): JsonResponse
    {
        $users = $this->likes->likedBy($post);

        return response()->json(UserResource::collection($users)->response()->getData(true));
    }
}
