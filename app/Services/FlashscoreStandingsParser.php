<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FlashscoreStandingsParser
{
    private const DEFAULT_FSIGN = 'SW9D1eZo';

    private const DEFAULT_FEED_HOST = 'https://46.flashscore.ninja';

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchStandings(string $standingsUrl): array
    {
        $html = $this->fetchPage($standingsUrl);
        if ($html === '') {
            return [];
        }

        $meta = $this->parsePageMeta($html);
        if ($meta['tournament_id'] === '' || $meta['stage_id'] === '') {
            Log::warning('Flashscore: tournament IDs not found', ['url' => $standingsUrl]);

            return [];
        }

        $feedUrl = sprintf(
            '%s/%d/x/feed/to_%s_%s_1',
            $meta['feed_host'],
            $meta['project_id'],
            $meta['tournament_id'],
            $meta['stage_id']
        );

        $feedBody = $this->fetchFeed($feedUrl, $standingsUrl, $meta['feed_sign']);
        if ($feedBody === '') {
            return [];
        }

        return $this->parseFeed($feedBody);
    }

    private function fetchPage(string $url): string
    {
        try {
            $response = Http::timeout(25)
                ->withHeaders([
                    'User-Agent' => $this->userAgent(),
                    'Accept-Language' => 'ru-RU,ru;q=0.9',
                ])
                ->get($url);

            return $response->successful() ? $response->body() : '';
        } catch (\Throwable $e) {
            Log::warning('Flashscore page fetch failed', ['url' => $url, 'error' => $e->getMessage()]);

            return '';
        }
    }

    private function fetchFeed(string $feedUrl, string $referer, string $fsign): string
    {
        try {
            $response = Http::timeout(25)
                ->withHeaders([
                    'Referer' => $referer,
                    'x-fsign' => $fsign,
                    'User-Agent' => $this->userAgent(),
                ])
                ->get($feedUrl);

            $body = $response->body();

            if (! $response->successful() || $body === '' || str_starts_with($body, '<')) {
                Log::warning('Flashscore feed empty or invalid', ['url' => $feedUrl, 'status' => $response->status()]);

                return '';
            }

            return $body;
        } catch (\Throwable $e) {
            Log::warning('Flashscore feed fetch failed', ['url' => $feedUrl, 'error' => $e->getMessage()]);

            return '';
        }
    }

    /**
     * @return array{feed_host: string, project_id: int, tournament_id: string, stage_id: string, feed_sign: string}
     */
    private function parsePageMeta(string $html): array
    {
        preg_match('/tournamentId:\s*"([^"]+)"/', $html, $tournamentId);
        preg_match('/tournamentStageId:\s*"([^"]+)"/', $html, $stageId);
        preg_match('/"feed_sign":"([^"]+)"/', $html, $feedSign);
        preg_match('/projectId:\s*(\d+)/', $html, $projectId);

        $host = self::DEFAULT_FEED_HOST;
        if (preg_match('/"feed_resolver"[^}]*"local"\:\[\{"url":"([^"]+)"/', $html, $feedHost)) {
            $host = str_replace('\/', '/', $feedHost[1]);
        }

        return [
            'feed_host' => rtrim($host, '/'),
            'project_id' => (int) ($projectId[1] ?? 46),
            'tournament_id' => $tournamentId[1] ?? '',
            'stage_id' => $stageId[1] ?? '',
            'feed_sign' => $feedSign[1] ?? self::DEFAULT_FSIGN,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseFeed(string $body): array
    {
        $rows = [];

        foreach (explode('~', $body) as $segment) {
            if (! str_contains($segment, 'TN÷')) {
                continue;
            }

            $fields = $this->parseSegment($segment);
            if (($fields['TN'] ?? '') === '') {
                continue;
            }

            [$gf, $ga] = $this->parseGoals($fields['TG'] ?? '0:0');

            $rows[] = [
                'rank' => (int) ($fields['TR'] ?? 0),
                'team' => $fields['TN'],
                'logo' => null,
                'played' => (int) ($fields['TM'] ?? 0),
                'won' => (int) ($fields['TW'] ?? 0),
                'drawn' => (int) ($fields['TDR'] ?? 0),
                'lost' => (int) ($fields['TL'] ?? 0),
                'gf' => $gf,
                'ga' => $ga,
                'gd' => $gf - $ga,
                'points' => (int) ($fields['TP'] ?? 0),
            ];
        }

        usort($rows, fn (array $a, array $b) => $a['rank'] <=> $b['rank']);

        return $rows;
    }

    /** @return array<string, string> */
    private function parseSegment(string $segment): array
    {
        $fields = [];

        foreach (explode('¬', $segment) as $part) {
            if (! str_contains($part, '÷')) {
                continue;
            }

            [$key, $value] = explode('÷', $part, 2);
            $fields[$key] = $value;
        }

        return $fields;
    }

    /** @return array{0: int, 1: int} */
    private function parseGoals(string $value): array
    {
        if (preg_match('/(\d+)\s*:\s*(\d+)/', $value, $m)) {
            return [(int) $m[1], (int) $m[2]];
        }

        return [0, 0];
    }

    private function userAgent(): string
    {
        return 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';
    }
}
