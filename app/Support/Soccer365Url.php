<?php

namespace App\Support;

class Soccer365Url
{
    public static function extractClubId(?string $url): ?int
    {
        if ($url === null || $url === '') {
            return null;
        }

        if (preg_match('~/clubs/(\d+)/?~', $url, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    public static function clubUrl(int $clubId): string
    {
        return 'https://soccer365.ru/clubs/'.$clubId.'/';
    }
}
