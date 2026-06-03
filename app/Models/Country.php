<?php

namespace App\Models;

use App\Support\Soccer365ImageUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    protected $fillable = [
        'source_country_id',
        'name',
        'slug',
        'flag_url',
    ];

    public function getFlagUrlAttribute(?string $value): ?string
    {
        return Soccer365ImageUrl::flag($this->source_country_id, $value);
    }

    public function clubs(): HasMany
    {
        return $this->hasMany(Club::class);
    }
}
