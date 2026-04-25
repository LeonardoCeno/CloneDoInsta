<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Story extends Model
{
    protected $fillable = ['user_id', 'image_path'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function views()
    {
        return $this->hasMany(StoryView::class);
    }

    public function getImageUrlAttribute(): string
    {
        return asset('storage/' . $this->image_path);
    }

    public function getExpiresAtAttribute(): ?\Illuminate\Support\Carbon
    {
        return $this->created_at?->addHours(24);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('created_at', '>=', now()->subHours(24));
    }
}
