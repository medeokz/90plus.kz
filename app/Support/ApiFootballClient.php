<?php

namespace App\Support;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class ApiFootballClient
{
    public static function isPaused(): bool
    {
        return (int) Cache::get('api_football.paused_until', 0) > time();
    }

    public static function pause(int $seconds): void
    {
        Cache::put('api_football.paused_until', time() + $seconds, $seconds);
    }

    public static function get(string $url, array $query = [], int $timeout = 20): Response
    {
        if (self::isPaused()) {
            return Http::response('', 429);
        }

        self::waitForSlot();

        $response = Http::timeout($timeout)
            ->withHeaders(['x-apisports-key' => config('football.api_football_key')])
            ->get($url, $query);

        if ($response->status() === 429) {
            $pause = (int) config('football.api_football_pause_seconds', 600);
            self::pause($pause);
            Log::warning('API-Football HTTP 429 — pausing '.$pause.'s');
        }

        return $response;
    }

    private static function waitForSlot(): void
    {
        $minMs = (int) config('football.api_football_min_interval_ms', 400);
        $last = (float) Cache::get('api_football.last_request_micro', 0);
        $elapsedMs = (microtime(true) - $last) * 1000;

        if ($elapsedMs < $minMs) {
            usleep((int) (($minMs - $elapsedMs) * 1000));
        }

        Cache::put('api_football.last_request_micro', microtime(true), 120);
    }
}
