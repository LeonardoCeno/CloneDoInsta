<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'image_path', 'caption'];

    public function user()
    {
        return $this->belongsTo(User::class);
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
        return $this->hasMany(Repost::class);
    }

    public function comments()
    {
        return $this->hasMany(Comment::class)->latest();
    }

    public function getImageUrlAttribute(): string
    {
        return 'https://storage.googleapis.com/' . config('filesystems.disks.gcs.bucket') . '/' . $this->image_path;
    }

    public function getIsVideoAttribute(): bool
    {
        return str_ends_with(strtolower($this->image_path ?? ''), '.mp4');
    }

    public function isLikedBy(User $user): bool
    {
        return $this->likes()->where('user_id', $user->id)->exists();
    }

    public function isSavedBy(User $user): bool
    {
        return $this->saves()->where('user_id', $user->id)->exists();
    }

    public function isRepostedBy(User $user): bool
    {
        return $this->reposts()->where('user_id', $user->id)->exists();
    }

    public function scopeWithPostCounts(Builder $query): void
    {
        $query->withCount(['likes', 'comments', 'reposts']);
    }

    public function scopeWithLikedByViewer(Builder $query, ?User $viewer): void
    {
        if ($viewer) {
            $query->withExists(['likes as liked_by_me' => fn ($q) => $q->where('user_id', $viewer->id)]);
        }
    }

    public function scopeWithSavedByViewer(Builder $query, ?User $viewer): void
    {
        if ($viewer) {
            $query->withExists(['saves as saved_by_me' => fn ($q) => $q->where('user_id', $viewer->id)]);
        }
    }

    public function scopeWithRepostedByViewer(Builder $query, ?User $viewer): void
    {
        if ($viewer) {
            $query->withExists(['reposts as reposted_by_me' => fn ($q) => $q->where('user_id', $viewer->id)]);
        }
    }
}
