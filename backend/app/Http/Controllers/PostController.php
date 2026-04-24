<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Services\PostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function __construct(private PostService $posts) {}

    public function store(StorePostRequest $request): JsonResponse
    {
        $post = $this->posts->create(
            $request->user(),
            $request->file('image'),
            $request->caption
        );

        $post->load('user')->loadCount(['likes', 'comments']);

        return response()->json(new PostResource($post), 201);
    }

    public function show(Request $request, Post $post): JsonResponse
    {
        $post = $this->posts->find($post->id, $request->user());

        return response()->json(new PostResource($post));
    }

    public function update(UpdatePostRequest $request, Post $post): JsonResponse
    {
        $this->authorize('modify', $post);

        $post = $this->posts->update($post, $request->caption);

        return response()->json(new PostResource($post));
    }

    public function destroy(Request $request, Post $post): JsonResponse
    {
        $this->authorize('modify', $post);

        $this->posts->delete($post);

        return response()->json(['message' => 'Post removido.']);
    }
}
