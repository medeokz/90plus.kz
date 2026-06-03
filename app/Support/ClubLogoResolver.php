<?php

namespace App\Support;

use App\Models\Club;

class ClubLogoResolver
{
    /** @var array<int, string|null> */
    private static array $cacheById = [];

    /** @var array<string, string|null> */
    private static array $cacheByName = [];

    public static function resolve(?string $logoUrl, ?string $clubPageUrl = null, ?int $sourceClubId = null, ?string $clubName = null): ?string
    {
        $id = $sourceClubId ?? Soccer365Url::extractClubId($clubPageUrl ?? '');

        if (! $id && $logoUrl && preg_match('~_32_(\d+)\.(png|svg|webp|jpe?g)$~i', $logoUrl, $m)) {
            $id = (int) $m[1];
        }

        if ($id) {
            if (! array_key_exists($id, self::$cacheById)) {
                self::$cacheById[$id] = Club::query()
                    ->where('source_club_id', $id)
                    ->value('logo_url');
            }

            if (self::$cacheById[$id]) {
                return self::$cacheById[$id];
            }
        }

        $normalized = self::normalizeTeamLogoUrl($logoUrl);
        if ($normalized) {
            return $normalized;
        }

        return self::resolveByName($clubName);
    }

    public static function resolveByName(?string $name): ?string
    {
        $name = trim($name ?? '');
        if ($name === '') {
            return null;
        }

        if (! array_key_exists($name, self::$cacheByName)) {
            self::$cacheByName[$name] = Club::query()
                ->where('name', $name)
                ->value('logo_url');
        }

        return self::$cacheByName[$name];
    }

    public static function normalizeTeamLogoUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        // _32_{clubId} is required on scr365 CDN — never strip it.
        return $url;
    }
}
