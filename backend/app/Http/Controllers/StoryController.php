<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStoryRequest;
use App\Http\Resources\StoryResource;
use App\Http\Resources\UserResource;
use App\Models\Story;
use App\Services\StoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoryController extends Controller
{
    public function __construct(private StoryService $stories) {}

    public function store(StoreStoryRequest $request): JsonResponse
    {
        $story = $this->stories->create($request->user(), $request->file('image'));
        $story->load('user');

        return response()->json(new StoryResource($story), 201);
    }

    public function feed(Request $request): JsonResponse
    {
        $groups = $this->stories->feedGroups($request->user());

        $data = $groups->map(fn ($g) => [
            'user'       => (new UserResource($g['user']))->toArray($request),
            'stories'    => $g['stories']->map(fn ($s) => (new StoryResource($s))->toArray($request))->values(),
            'has_unseen' => $g['has_unseen'],
        ]);

        return response()->json(['data' => $data]);
    }

    public function markSeen(Request $request, Story $story): JsonResponse
    {
        $this->stories->markSeen($request->user(), $story);

        return response()->json(['ok' => true]);
    }

    public function destroy(Story $story): JsonResponse
    {
        $this->authorize('delete', $story);

        $this->stories->delete($story);

        return response()->json(['message' => 'Story removido.']);
    }
}
