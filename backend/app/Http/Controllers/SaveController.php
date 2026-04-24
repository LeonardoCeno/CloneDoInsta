<?php

namespace App\Http\Controllers;

use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Services\SaveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaveController extends Controller
{
    public function __construct(private SaveService $saves) {}

    public function store(Request $request, Post $post): JsonResponse
    {
        $this->saves->save($request->user(), $post);

        return response()->json(['saved' => true]);
    }

    public function destroy(Request $request, Post $post): JsonResponse
    {
        $this->saves->unsave($request->user(), $post);

        return response()->json(['saved' => false]);
    }

    public function mySaved(Request $request): JsonResponse
    {
        $posts = $this->saves->savedByUser($request->user(), $this->perPage($request, 15));

        return response()->json(PostResource::collection($posts)->response()->getData(true));
    }
}
