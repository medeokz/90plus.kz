<?php

namespace App\Support;

class NationalTeamFlags
{
    private const BASE_URL = 'https://media.api-sports.io/flags/';

    /** @var array<string, string> */
    private const CODES = [
        'Algeria' => 'dz',
        'Argentina' => 'ar',
        'Australia' => 'au',
        'Austria' => 'at',
        'Belgium' => 'be',
        'Bosnia and Herzegovina' => 'ba',
        'Brazil' => 'br',
        'Canada' => 'ca',
        'Cape Verde' => 'cv',
        'Colombia' => 'co',
        'Croatia' => 'hr',
        'Curaçao' => 'cw',
        'Curacao' => 'cw',
        'Czech Republic' => 'cz',
        'Czechia' => 'cz',
        'DR Congo' => 'cd',
        'Ecuador' => 'ec',
        'Egypt' => 'eg',
        'England' => 'gb-eng',
        'France' => 'fr',
        'Germany' => 'de',
        'Ghana' => 'gh',
        'Haiti' => 'ht',
        'Iran' => 'ir',
        'Iraq' => 'iq',
        'Ivory Coast' => 'ci',
        "Côte d'Ivoire" => 'ci',
        'Cote d\'Ivoire' => 'ci',
        'Japan' => 'jp',
        'Jordan' => 'jo',
        'Mexico' => 'mx',
        'Morocco' => 'ma',
        'Netherlands' => 'nl',
        'New Zealand' => 'nz',
        'Norway' => 'no',
        'Panama' => 'pa',
        'Paraguay' => 'py',
        'Portugal' => 'pt',
        'Qatar' => 'qa',
        'Saudi Arabia' => 'sa',
        'Scotland' => 'gb-sct',
        'Senegal' => 'sn',
        'South Africa' => 'za',
        'South Korea' => 'kr',
        'Korea Republic' => 'kr',
        'Spain' => 'es',
        'Sweden' => 'se',
        'Switzerland' => 'ch',
        'Tunisia' => 'tn',
        'Turkey' => 'tr',
        'Türkiye' => 'tr',
        'USA' => 'us',
        'United States' => 'us',
        'Uruguay' => 'uy',
        'Uzbekistan' => 'uz',
    ];

    public static function url(?string $team): ?string
    {
        if ($team === null || $team === '') {
            return null;
        }

        $code = self::CODES[$team] ?? null;

        if ($code === null) {
            $normalized = trim(preg_replace('/\s+/', ' ', $team) ?? $team);
            $code = self::CODES[$normalized] ?? null;
        }

        return $code ? self::BASE_URL.strtolower($code).'.svg' : null;
    }
}
