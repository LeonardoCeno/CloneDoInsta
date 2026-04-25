<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'bio',
        'avatar_path',
        'is_private',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password'   => 'hashed',
            'is_private' => 'boolean',
        ];
    }

    public function posts()
    {
        return $this->hasMany(Post::class)->latest();
    }

    public function likes()
    {
        return $this->hasMany(Like::class);
    }

    public function saves()
    {
        return $this->hasMany(Save::class);
    }

    public function reposts()
    {
        return $this->hasMany(Repost::class)->latest('created_at');
    }

    public function stories()
    {
        return $this->hasMany(Story::class)->latest();
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class)->latest('created_at');
    }

    public function following()
    {
        return $this->belongsToMany(User::class, 'follows', 'follower_id', 'following_id')
                    ->wherePivot('status', 'accepted')
                    ->withPivot('created_at');
    }

    public function followers()
    {
        return $this->belongsToMany(User::class, 'follows', 'following_id', 'follower_id')
                    ->wherePivot('status', 'accepted')
                    ->withPivot('created_at');
    }

    public function pendingFollowers()
    {
        return $this->belongsToMany(User::class, 'follows', 'following_id', 'follower_id')
                    ->wherePivot('status', 'pending')
                    ->withPivot('created_at');
    }

    public function isFollowing(User $user): bool
    {
        return $this->following()->where('following_id', $user->id)->exists();
    }

    public function isFollowPending(User $user): bool
    {
        return DB::table('follows')
            ->where('follower_id', $this->id)
            ->where('following_id', $user->id)
            ->where('status', 'pending')
            ->exists();
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar_path
            ? asset('storage/' . $this->avatar_path)
            : null;
    }

    public function scopeWithUserCounts(Builder $query): void
    {
        $query->withCount(['posts', 'followers', 'following']);
    }

    public function scopeWithFollowedByViewer(Builder $query, ?User $viewer): void
    {
        if ($viewer) {
            $query->withExists(['followers as is_followed_by_viewer' => fn ($q) => $q->where('follower_id', $viewer->id)]);
        }
    }

    public function scopeWithFollowPendingByViewer(Builder $query, ?User $viewer): void
    {
        if ($viewer) {
            $query->withExists(['pendingFollowers as is_follow_pending_by_viewer' => fn ($q) => $q->where('follower_id', $viewer->id)]);
        }
    }
}
