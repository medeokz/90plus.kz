<?php

namespace App\Models;

use App\Support\Soccer365ImageUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Player extends Model
{
    protected $fillable = [
        'source_player_id',
        'name',
        'slug',
        'photo_url',
        'nationality',
        'nationality_flag_url',
        'age',
        'source_url',
    ];

    public function clubs(): BelongsToMany
    {
        return $this->belongsToMany(Club::class)
            ->withPivot(['position', 'number', 'age', 'nationality', 'season', 'parsed_at'])
            ->withTimestamps();
    }

    public function getPhotoUrlAttribute(?string $value): ?string
    {
        return Soccer365ImageUrl::playerPhoto($value);
    }

    public function getNationalityFlagUrlAttribute(?string $value): ?string
    {
        return Soccer365ImageUrl::flag(null, $value);
    }
}

