<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Fixture extends Model
{
    protected $fillable = [
        'external_id',
        'api_fixture_id',
        'competition',
        'home_team',
        'away_team',
        'home_team_flag',
        'away_team_flag',
        'home_score',
        'away_score',
        'status',
        'minute',
        'kickoff_at',
        'venue',
        'city',
        'weather',
        'temperature',
        'broadcast',
        'referees',
        'events',
        'lineups',
        'statistics',
        'team_form',
    ];

    protected function casts(): array
    {
        return [
            'kickoff_at' => 'datetime',
            'referees' => 'array',
            'events' => 'array',
            'lineups' => 'array',
            'statistics' => 'array',
            'team_form' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'external_id';
    }

    public function isLive(): bool
    {
        return in_array($this->status, ['LIVE', '1H', '2H', 'HT', 'ET', 'BT', 'P'], true);
    }

    public function isFinished(): bool
    {
        return in_array($this->status, ['FT', 'AET', 'PEN'], true);
    }

    public function statusLabel(): string
    {
        if ($this->isLive() && $this->minute) {
            return $this->minute."'";
        }

        return match ($this->status) {
            'FT' => 'Аяқталды',
            'HT' => 'Үзіліс',
            'NS' => 'Басталмады',
            default => $this->status,
        };
    }
}
