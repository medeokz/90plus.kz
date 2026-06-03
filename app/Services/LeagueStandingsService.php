<?php

namespace App\Services;

use App\Support\ApiFootballClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class LeagueStandingsService
{
    public function getAll(): array
    {
        $leagues = config('football.leagues', []);
        $result = [];

        foreach ($leagues as $league) {
            $result[] = [
                'key' => $league['key'],
                'name' => $league['name'],
                'short' => $league['short'] ?? $league['name'],
                'flag' => $league['flag'] ?? $league['key'],
                'country' => $league['country'] ?? '',
                'standings' => $this->getStandings($league),
            ];
        }

        return $result;
    }

    /** @return array<int, array<string, mixed>> */
    public function standingsFor(string $key): array
    {
        $league = collect(config('football.leagues', []))->firstWhere('key', $key);

        return $league ? $this->getStandings($league) : [];
    }

    /** @param array{key: string, api_id: int, api_season?: int} $league */
    public function resolveSeasonForLeague(array $league): ?int
    {
        if (! empty($league['api_season'])) {
            return (int) $league['api_season'];
        }

        if (! empty($league['api_id'])) {
            return $this->resolveCurrentSeason((int) $league['api_id']);
        }

        return null;
    }

    /** @return int Количество обновлённых лиг */
    public function syncAll(): int
    {
        if (! config('football.api_football_key')) {
            return 0;
        }

        $updated = 0;

        foreach (config('football.leagues', []) as $league) {
            Cache::forget('flashscore.standings.'.$league['key']);

            $standings = $this->fetchFromApiFootball($league);

            if (empty($standings)) {
                continue;
            }

            Cache::put(
                'league.standings.'.$league['key'],
                $standings,
                now()->addMinutes(30)
            );

            $updated++;

            sleep(7);
        }

        if ($updated > 0) {
            Cache::put('league.standings.updated_at', now()->toIso8601String(), now()->addHours(6));
        }

        return $updated;
    }

    /** @param array{key: string, name: string, api_id: int, api_season?: int} $league */
    private function getStandings(array $league): array
    {
        $cacheKey = 'league.standings.'.$league['key'];

        return Cache::remember($cacheKey, 1800, function () use ($league) {
            return $this->fetchFromApiFootball($league);
        });
    }

    /** @param array{key: string, api_id: int, api_season?: int} $league */
    private function fetchFromApiFootball(array $league): array
    {
        $apiKey = config('football.api_football_key');
        if (! $apiKey || empty($league['api_id'])) {
            return [];
        }

        foreach ($this->seasonsToTry($league) as $season) {
            $rows = $this->requestStandings($league['api_id'], $season);

            if (! empty($rows)) {
                Cache::put('league.standings.season.'.$league['key'], $season, now()->addDay());

                return $this->normalizeApiFootballRows($rows);
            }
        }

        return [];
    }

    /** @param array{key: string, api_id: int, api_season?: int} $league */
    private function seasonsToTry(array $league): array
    {
        $cached = Cache::get('league.standings.season.'.$league['key']);
        if ($cached) {
            return [(int) $cached];
        }

        $candidates = [];

        if (! empty($league['api_season'])) {
            $candidates[] = (int) $league['api_season'];
        }

        $current = $this->resolveCurrentSeason($league['api_id']);
        if ($current !== null) {
            $candidates[] = $current;
        }

        $year = (int) now()->format('Y');
        $candidates[] = $year - 1;
        $candidates[] = $year - 2;

        return array_slice(array_values(array_unique(array_filter($candidates))), 0, 3);
    }

    private function resolveCurrentSeason(int $leagueId): ?int
    {
        return Cache::remember("league.season.{$leagueId}", 86400, function () use ($leagueId) {
            try {
                $response = ApiFootballClient::get(
                    'https://v3.football.api-sports.io/leagues',
                    ['id' => $leagueId]
                );

                if (! $response->successful()) {
                    return null;
                }

                $seasons = $response->json('response.0.seasons') ?? [];

                foreach ($seasons as $season) {
                    if (! empty($season['current'])) {
                        return (int) $season['year'];
                    }
                }

                $latest = collect($seasons)->sortByDesc('year')->first();

                return isset($latest['year']) ? (int) $latest['year'] : null;
            } catch (\Throwable $e) {
                Log::warning('API-Football league season failed', ['league' => $leagueId, 'error' => $e->getMessage()]);

                return null;
            }
        });
    }

    /** @return array<int, array<string, mixed>> */
    private function requestStandings(int $leagueId, int $season, int $attempt = 0): array
    {
        try {
            if (ApiFootballClient::isPaused()) {
                return [];
            }

            $response = ApiFootballClient::get(
                'https://v3.football.api-sports.io/standings',
                [
                    'league' => $leagueId,
                    'season' => $season,
                ]
            );

            if (! $response->successful()) {
                return [];
            }

            $errors = $response->json('errors') ?? [];

            if (isset($errors['rateLimit']) && $attempt < 1) {
                sleep(65);

                return $this->requestStandings($leagueId, $season, $attempt + 1);
            }

            if (! empty($errors)) {
                return [];
            }

            return $response->json('response.0.league.standings.0') ?? [];
        } catch (\Throwable $e) {
            Log::warning('API-Football standings failed', [
                'league' => $leagueId,
                'season' => $season,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    private function normalizeApiFootballRows(array $rows): array
    {
        return array_map(fn (array $row) => [
            'rank' => $row['rank'],
            'team' => $row['team']['name'],
            'logo' => $row['team']['logo'] ?? null,
            'played' => $row['all']['played'],
            'won' => $row['all']['win'],
            'drawn' => $row['all']['draw'],
            'lost' => $row['all']['lose'],
            'gf' => $row['all']['goals']['for'],
            'ga' => $row['all']['goals']['against'],
            'gd' => $row['goalsDiff'],
            'points' => $row['points'],
        ], $rows);
    }
}
