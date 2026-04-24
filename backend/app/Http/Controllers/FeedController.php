<?php

namespace App\Http\Controllers;

use App\Http\Resources\PostResource;
use App\Services\FeedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeedController extends Controller
{
    public function __construct(private FeedService $feed) {}

    public function index(Request $request): JsonResponse
    {
        $posts = $this->feed->feed($request->user(), $this->perPage($request, 15, 50));

        return response()->json(PostResource::collection($posts)->response()->getData(true));
    }
}
