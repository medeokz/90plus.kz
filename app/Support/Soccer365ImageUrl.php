<?php

namespace App\Support;

class Soccer365ImageUrl
{
    public static function flag(int|string|null $sourceId, ?string $url = null): ?string
    {
        if ($sourceId !== null && $sourceId !== '') {
            $id = (int) $sourceId;
            if ($id > 0) {
                return 'https://s.scr365.net/img/flags/'.$id.'.svg';
            }
        }

        if ($url && preg_match('~/flags/(\d+)~', $url, $m)) {
            return 'https://s.scr365.net/img/flags/'.$m[1].'.svg';
        }

        return $url;
    }

    public static function playerPhoto(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $upgraded = preg_replace('/_(mini|thumb)(\.(png|jpe?g|webp))$/i', '$2', $url);

        return $upgraded ?: $url;
    }

    public static function clubLogo(?string $url): ?string
    {
        return ClubLogoResolver::normalizeTeamLogoUrl($url);
    }
}
