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

    public function comments()
    {
        return $this->hasMany(Comment::class)->latest();
    }

    public function getImageUrlAttribute(): string
    {
        return asset('storage/' . $this->image_path);
    }

    public function isLikedBy(User $user): bool
    {
        return $this->likes()->where('user_id', $user->id)->exists();
    }

    public function scopeWithPostCounts(Builder $query): void
    {
        $query->withCount(['likes', 'comments']);
    }

    public function scopeWithLikedByViewer(Builder $query, ?User $viewer): void
    {
        if ($viewer) {
            $query->withExists(['likes as liked_by_me' => fn ($q) => $q->where('user_id', $viewer->id)]);
        }
    }
}
