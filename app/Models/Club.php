<?php

namespace App\Models;

use App\Support\Soccer365ImageUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Club extends Model
{
    protected $fillable = [
        'source_club_id',
        'name',
        'name_en',
        'description',
        'profile_data',
        'slug',
        'logo_url',
        'country_id',
        'country',
        'city',
        'source_url',
    ];

    protected $casts = [
        'profile_data' => 'array',
    ];

    public function countryRecord(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function players(): BelongsToMany
    {
        return $this->belongsToMany(Player::class)
            ->withPivot(['position', 'number', 'age', 'nationality', 'season', 'parsed_at'])
            ->withTimestamps();
    }

    public function transfersOut(): HasMany
    {
        return $this->hasMany(Transfer::class, 'from_club_source_id', 'source_club_id');
    }

    public function transfersIn(): HasMany
    {
        return $this->hasMany(Transfer::class, 'to_club_source_id', 'source_club_id');
    }

    public function getLogoUrlAttribute(?string $value): ?string
    {
        return Soccer365ImageUrl::clubLogo($value);
    }
}
