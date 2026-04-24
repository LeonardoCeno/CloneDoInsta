<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCommentRequest;
use App\Http\Requests\UpdateCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\Post;
use App\Services\CommentService;
use Illuminate\Http\JsonResponse;

class CommentController extends Controller
{
    public function __construct(private CommentService $comments) {}

    public function store(StoreCommentRequest $request, Post $post): JsonResponse
    {
        $comment = $this->comments->create($request->user(), $post, $request->body);
        $comment->load('user');

        return response()->json(new CommentResource($comment), 201);
    }

    public function index(Post $post): JsonResponse
    {
        $comments = $this->comments->byPost($post);

        return response()->json(CommentResource::collection($comments)->response()->getData(true));
    }

    public function update(UpdateCommentRequest $request, Comment $comment): JsonResponse
    {
        $this->authorize('modify', $comment);

        $comment = $this->comments->update($comment, $request->body);

        return response()->json(new CommentResource($comment));
    }

    public function destroy(Comment $comment): JsonResponse
    {
        $this->authorize('modify', $comment);

        $this->comments->delete($comment);

        return response()->json(['message' => 'Comentário removido.']);
    }
}
