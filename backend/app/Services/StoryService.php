<?php

namespace App\Services;

use App\Models\Story;
use App\Models\StoryView;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StoryService
{
    public function create(User $user, UploadedFile $image): Story
    {
        $path = $image->storeAs(
            'stories',
            Str::uuid() . '.' . $image->getClientOriginalExtension(),
            'gcs'
        );

        return Story::create(['user_id' => $user->id, 'image_path' => $path]);
    }

    public function feedGroups(User $viewer): Collection
    {
        $userIds = $viewer->following()->pluck('users.id')->prepend($viewer->id);

        $stories = Story::whereIn('user_id', $userIds)
            ->active()
            ->with('user')
            ->withExists(['views as seen_by_me' => fn ($q) => $q->where('user_id', $viewer->id)])
            ->oldest()
            ->get();

        return $stories
            ->groupBy('user_id')
            ->map(function ($userStories) use ($viewer) {
                $hasUnseen = $userStories->contains(fn ($s) => !(bool) $s->seen_by_me);
                return [
                    'user'       => $userStories->first()->user,
                    'stories'    => $userStories->values(),
                    'has_unseen' => $hasUnseen,
                ];
            })
            ->sortBy(fn ($g) => [
                $g['user']->id === $viewer->id ? 0 : 1,
                $g['has_unseen'] ? 0 : 1,
            ])
            ->values();
    }

    public function markSeen(User $viewer, Story $story): void
    {
        StoryView::firstOrCreate([
            'user_id'  => $viewer->id,
            'story_id' => $story->id,
        ]);
    }

    public function delete(Story $story): void
    {
        Storage::disk('gcs')->delete($story->image_path);
        $story->delete();
    }
}
