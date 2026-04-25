<?php

namespace App\Http\Controllers;

use App\Http\Resources\PostResource;
use App\Services\PostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExploreController extends Controller
{
    public function __construct(private PostService $posts) {}

    public function index(Request $request): JsonResponse
    {
        $posts = $this->posts->explore($this->perPage($request, 18), $request->user());

        return response()->json(PostResource::collection($posts)->response()->getData(true));
    }
}
