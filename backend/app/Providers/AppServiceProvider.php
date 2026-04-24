<?php

namespace App\Providers;

use App\Models\Comment;
use App\Models\Like;
use App\Observers\CommentObserver;
use App\Observers\LikeObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Like::observe(LikeObserver::class);
        Comment::observe(CommentObserver::class);
    }
}
