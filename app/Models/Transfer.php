<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transfer extends Model
{
    protected $fillable = [
        'season',
        'player_name',
        'player_url',
        'player_icon',
        'position',
        'from_club',
        'from_club_source_id',
        'from_club_url',
        'from_club_icon',
        'to_club',
        'to_club_source_id',
        'to_club_url',
        'to_club_icon',
        'transfer_date',
        'date_text',
        'fee',
        'country',
        'source_url',
        'fingerprint',
        'parsed_at',
    ];

    protected function casts(): array
    {
        return [
            'transfer_date' => 'date',
            'parsed_at' => 'datetime',
        ];
    }

    public function fromClub(): BelongsTo
    {
        return $this->belongsTo(Club::class, 'from_club_source_id', 'source_club_id');
    }

    public function toClub(): BelongsTo
    {
        return $this->belongsTo(Club::class, 'to_club_source_id', 'source_club_id');
    }
}
