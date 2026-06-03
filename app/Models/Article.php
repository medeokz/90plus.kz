<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Article extends Model
{
    protected $fillable = [
        'title_en',
        'title_kk',
        'summary_en',
        'summary_kk',
        'content_en',
        'content_kk',
        'source_url',
        'source_name',
        'image_url',
        'slug',
        'published_at',
        'status',
        'fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'fetched_at' => 'datetime',
        ];
    }

    protected $appends = ['image_display'];

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ArticleComment::class);
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(ArticleReaction::class);
    }

    public function approvedComments(): HasMany
    {
        return $this->comments()->approved()->latest();
    }

    public static function generateSlug(string $title): string
    {
        $slug = Str::slug(Str::limit($title, 80, ''));
        $original = $slug;
        $count = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = $original.'-'.$count++;
        }

        return $slug;
    }

    /** URL для отображения: локальный файл или внешняя ссылка. */
    public function getImageDisplayAttribute(): ?string
    {
        $path = $this->attributes['image_url'] ?? null;
        if ($path === null || $path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return asset(ltrim($path, '/'));
    }
}
