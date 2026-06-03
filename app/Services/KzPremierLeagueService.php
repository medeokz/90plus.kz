<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KzPremierLeagueService
{
    private const CACHE_KEY = 'competition.kz-premier-liga';

    public function __construct(
        private readonly LeagueStandingsService $standingsService,
    ) {}

    /** @return array<string, mixed> */
    public function config(): array
    {
        return config('football.competitions.kz-premier-liga', []);
    }

    /** @return array<string, mixed> */
    public function leagueConfig(): array
    {
        $key = $this->config()['league_key'] ?? 'kz';

        return collect(config('football.leagues', []))->firstWhere('key', $key) ?? [];
    }

    /** @return array<string, mixed> */
    public function getPageData(string $tab = 'tournament'): array
    {
        $aggressive = in_array($tab, ['schedule', 'results'], true);
        $this->syncIfNeeded($aggressive);

        $fixtures = $this->getFixtures();
        $finished = count(array_filter($fixtures, fn (array $f) => $this->isFinished($f)));
        $meta = $this->seedData()['meta'];
        $season = $this->resolvedSeason();
        $standings = $this->getStandings();

        return [
            'competition' => $this->config(),
            'league' => $this->leagueConfig(),
            'tab' => $tab,
            'season' => $season,
            'overview' => [
                'season_label' => (string) ($meta['season_label'] ?? $season),
                'start_date' => $meta['start_date'] ?? null,
                'end_date' => $meta['end_date'] ?? null,
                'total_matches' => $meta['total_matches'] ?? count($fixtures) ?: 240,
                'played' => $finished,
                'stage' => $this->currentStage($fixtures, $meta['stage'] ?? 'Тұрақты маусым'),
                'country' => $meta['country'] ?? 'Қазақстан',
                'source' => Cache::get(self::CACHE_KEY.'.source', 'seed'),
            ],
            'standings' => $standings,
            'schedule' => $this->filterFixtures($fixtures, 'schedule'),
            'results' => $this->filterFixtures($fixtures, 'results'),
            'schedule_preview' => $this->flatFixtures($this->filterFixtures($fixtures, 'schedule'), 5),
            'results_preview' => $this->flatFixtures($this->filterFixtures($fixtures, 'results'), 5),
            'teams' => $this->getTeams(),
            'stadiums' => $this->getStadiums(),
        ];
    }

    public function syncFromApi(): bool
    {
        $apiKey = config('football.api_football_key');
        $leagueId = (int) ($this->leagueConfig()['api_id'] ?? $this->config()['api_league_id'] ?? 389);
        $targetSeason = $this->targetSeason();

        if (! $apiKey || ! $leagueId) {
            return false;
        }

        try {
            $headers = ['x-apisports-key' => $apiKey];
            $batches = [];

            foreach ($this->seasonsToTryForApi() as $season) {
                $items = $this->requestApiFixtures($headers, [
                    'league' => $leagueId,
                    'season' => $season,
                ]);

                if ($items !== []) {
                    $batches[] = $items;
                }
            }

            $from = now()->subDays(21)->format('Y-m-d');
            $to = now()->addDays(28)->format('Y-m-d');
            $byDate = $this->requestApiFixtures($headers, [
                'league' => $leagueId,
                'from' => $from,
                'to' => $to,
            ]);

            if ($byDate !== []) {
                $batches[] = $byDate;
            }

            $live = $this->requestLiveLeagueFixtures($headers, $leagueId);

            if ($live !== []) {
                $batches[] = $live;
            }

            $normalized = $this->filterToSeasonYear(
                $this->mergeFixtures(...$batches),
                $targetSeason
            );

            if ($normalized === []) {
                Cache::put(self::CACHE_KEY.'.source', 'seed', 3600);
                Cache::forget(self::CACHE_KEY.'.fixtures');
                Cache::forget(self::CACHE_KEY.'.merged.'.$targetSeason);

                return false;
            }

            $existing = Cache::get(self::CACHE_KEY.'.fixtures');
            $merged = is_array($existing)
                ? $this->mergeFixtures($existing, $normalized)
                : $normalized;
            $merged = $this->filterToSeasonYear($merged, $targetSeason);

            Cache::put(self::CACHE_KEY.'.fixtures', $merged, $this->cacheTtl());
            Cache::put(self::CACHE_KEY.'.season', $targetSeason, $this->cacheTtl());
            Cache::put(self::CACHE_KEY.'.source', 'api', $this->cacheTtl());
            Cache::forget(self::CACHE_KEY.'.merged.'.$targetSeason);

            $teamsSeason = $this->seasonsToTryForApi()[0];

            $teamsResp = Http::timeout(25)->withHeaders($headers)
                ->get('https://v3.football.api-sports.io/teams', [
                    'league' => $leagueId,
                    'season' => $teamsSeason,
                ]);

            $teams = $teamsResp->json('response') ?? [];

            if (! empty($teams)) {
                Cache::put(self::CACHE_KEY.'.teams', $this->normalizeApiTeams($teams), $this->cacheTtl());
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('KZ Premier League API sync failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function syncIfNeeded(bool $aggressive = false): void
    {
        if ($aggressive) {
            Cache::forget(self::CACHE_KEY.'.sync_lock');
        }

        $lockTtl = $aggressive ? 45 : min($this->cacheTtl(), 300);

        Cache::remember(self::CACHE_KEY.'.sync_lock', $lockTtl, function () {
            $this->syncFromApi();

            return true;
        });
    }

    public function targetSeason(): int
    {
        $configSeason = $this->config()['api_season'] ?? null;
        $metaSeason = $this->seedData()['meta']['season_label'] ?? null;

        return (int) ($configSeason ?? $metaSeason ?? 2026);
    }

    public function resolvedSeason(): int
    {
        return $this->targetSeason();
    }

    /** @return array<int> */
    private function seasonsToTryForApi(): array
    {
        $target = $this->targetSeason();

        return array_values(array_unique([$target, $target - 1]));
    }

    /** @return array<int, array<string, mixed>> */
    private function getStandings(): array
    {
        $seed = $this->seedData()['standings'] ?? [];

        if ($seed !== []) {
            return $seed;
        }

        $key = $this->config()['league_key'] ?? 'kz';

        return $this->standingsService->standingsFor($key);
    }

    /** @return array<int, array<string, mixed>> */
    private function getFixtures(): array
    {
        return $this->loadFixtures();
    }

    /** @return array<int, array<string, mixed>> */
    private function loadFixtures(): array
    {
        $targetSeason = $this->targetSeason();
        $apiFixtures = Cache::get(self::CACHE_KEY.'.fixtures');

        if (is_array($apiFixtures) && $apiFixtures !== []) {
            $filtered = $this->filterToSeasonYear($apiFixtures, $targetSeason);

            if ($filtered !== []) {
                return $this->mergeFixtures($this->buildSeedFixtures(), $filtered);
            }
        }

        return $this->buildSeedFixtures();
    }

    /**
     * @param  array<string, string>  $params
     * @param  array<string, string>  $headers
     * @return array<int, array<string, mixed>>
     */
    private function requestApiFixtures(array $headers, array $params): array
    {
        $response = Http::timeout(25)->withHeaders($headers)
            ->get('https://v3.football.api-sports.io/fixtures', $params);

        if (! $response->successful()) {
            return [];
        }

        $errors = $response->json('errors') ?? [];

        if (! empty($errors)) {
            return [];
        }

        $items = $response->json('response') ?? [];

        return array_map(fn (array $item) => $this->normalizeApiFixture($item), $items);
    }

    /**
     * @param  array<string, string>  $headers
     * @return array<int, array<string, mixed>>
     */
    private function requestLiveLeagueFixtures(array $headers, int $leagueId): array
    {
        $response = Http::timeout(20)->withHeaders($headers)
            ->get('https://v3.football.api-sports.io/fixtures', ['live' => 'all']);

        if (! $response->successful()) {
            return [];
        }

        $items = array_filter(
            $response->json('response') ?? [],
            fn (array $item) => (int) ($item['league']['id'] ?? 0) === $leagueId
        );

        return array_map(fn (array $item) => $this->normalizeApiFixture($item), $items);
    }

    /** @param  array<int, array<string, mixed>>  ...$lists */
    private function mergeFixtures(array ...$lists): array
    {
        $map = [];

        foreach ($lists as $list) {
            foreach ($list as $fixture) {
                $key = (string) ($fixture['match_id'] ?? $this->fixtureMergeKey($fixture));
                $map[$key] = isset($map[$key])
                    ? array_merge($map[$key], array_filter($fixture, fn ($v) => $v !== null))
                    : $fixture;
            }
        }

        return array_values($map);
    }

    /** @param  array<string, mixed>  $fixture */
    private function fixtureMergeKey(array $fixture): string
    {
        return md5(
            ($fixture['home_team'] ?? '').'|'.
            ($fixture['away_team'] ?? '').'|'.
            ($fixture['kickoff_at'] ?? '')
        );
    }

    /** @param  array<int, array<string, mixed>>  $fixtures */
    private function filterToSeasonYear(array $fixtures, int $year): array
    {
        return array_values(array_filter(
            $fixtures,
            fn (array $f) => $f['kickoff_at'] && str_starts_with((string) $f['kickoff_at'], (string) $year)
        ));
    }

    /** @param array<int, array<string, mixed>> $fixtures */
    private function fixturesBelongToSeason(array $fixtures, int $season): bool
    {
        if ($fixtures === []) {
            return false;
        }

        $inSeason = 0;

        foreach ($fixtures as $fixture) {
            $kickoff = $fixture['kickoff_at'] ?? null;

            if ($kickoff && str_starts_with((string) $kickoff, (string) $season)) {
                $inSeason++;
            }
        }

        return $inSeason > count($fixtures) / 2;
    }

    /** @return array<int, array<string, mixed>> */
    private function buildSeedFixtures(): array
    {
        $seed = $this->seedData();
        $fixtures = [];
        $id = 1;

        foreach ($seed['fixtures'] ?? [] as $row) {
            $status = $row['status'] ?? 'NS';
            $roundNum = $row['round'] ?? '';
            $roundLabel = $roundNum !== '' ? 'Тұрақты маусым, '.$roundNum.'-тур' : 'Тұрақты маусым';

            $fixtures[] = [
                'match_id' => $id,
                'round' => $roundLabel,
                'home_team' => $row['home'],
                'away_team' => $row['away'],
                'home_team_flag' => null,
                'away_team_flag' => null,
                'home_score' => $row['home_score'] ?? null,
                'away_score' => $row['away_score'] ?? null,
                'status' => $status,
                'minute' => null,
                'kickoff_at' => $row['at'],
                'venue' => null,
                'competition' => 'ҚПЛ · '.$roundLabel,
            ];
            $id++;
        }

        return $fixtures;
    }

    /** @return array<int, array<string, mixed>> */
    private function getTeams(): array
    {
        $teams = Cache::get(self::CACHE_KEY.'.teams');

        if (is_array($teams) && ! empty($teams)) {
            usort($teams, fn ($a, $b) => strcmp($a['name'] ?? '', $b['name'] ?? ''));

            return $teams;
        }

        $fromStandings = array_map(fn (array $row) => [
            'id' => null,
            'name' => $row['team'],
            'logo' => $row['logo'] ?? null,
            'founded' => null,
            'venue' => null,
        ], $this->getStandings());

        usort($fromStandings, fn ($a, $b) => strcmp($a['name'], $b['name']));

        return $fromStandings;
    }

    /** @return array<int, array<string, mixed>> */
    private function getStadiums(): array
    {
        $stadiums = [];
        $teams = $this->getTeams();

        foreach ($teams as $team) {
            $venue = $team['venue'] ?? null;

            if (! is_array($venue) || empty($venue['name'])) {
                continue;
            }

            $id = $venue['id'] ?? $venue['name'];

            if (! isset($stadiums[$id])) {
                $stadiums[$id] = array_merge($venue, ['teams' => []]);
            }

            $stadiums[$id]['teams'][] = $team['name'];
        }

        $list = array_values($stadiums);
        usort($list, fn ($a, $b) => strcmp($a['name'] ?? '', $b['name'] ?? ''));

        return $list;
    }

    /** @param array<int, array<string, mixed>> $fixtures */
    private function filterFixtures(array $fixtures, string $type): array
    {
        $filtered = array_values(array_filter($fixtures, function (array $f) use ($type) {
            if ($type === 'schedule') {
                return ! $this->isFinished($f);
            }

            return $this->isFinished($f);
        }));

        usort($filtered, function (array $a, array $b) use ($type) {
            $at = strtotime($a['kickoff_at'] ?? '');
            $bt = strtotime($b['kickoff_at'] ?? '');

            return $type === 'results' ? $bt <=> $at : $at <=> $bt;
        });

        $grouped = [];

        foreach ($filtered as $fixture) {
            $date = Carbon::parse($fixture['kickoff_at'])->format('d.m.Y');
            $grouped[$date][] = $fixture;
        }

        return $grouped;
    }

    /** @param array<string, array<int, array<string, mixed>>> $grouped */
    private function flatFixtures(array $grouped, int $limit): array
    {
        $flat = [];

        foreach ($grouped as $fixtures) {
            foreach ($fixtures as $fixture) {
                $flat[] = $fixture;

                if (count($flat) >= $limit) {
                    return $flat;
                }
            }
        }

        return $flat;
    }

    /** @param array<int, array<string, mixed>> $fixtures */
    private function currentStage(array $fixtures, string $fallback): string
    {
        $live = array_filter($fixtures, fn (array $f) => $this->isLive($f));

        if (! empty($live)) {
            return 'Ойын өтіп жатыр';
        }

        $upcoming = array_filter($fixtures, fn (array $f) => ! $this->isFinished($f));

        if (empty($upcoming)) {
            return $fixtures === [] ? $fallback : 'Маусым аяқталды';
        }

        usort($upcoming, fn ($a, $b) => strtotime($a['kickoff_at']) <=> strtotime($b['kickoff_at']));
        $next = reset($upcoming);

        if (! empty($next['round'])) {
            return $this->translateRound($next['round']);
        }

        return $fallback;
    }

    private function translateRound(string $round): string
    {
        if (preg_match('/Regular Season\s*-\s*(\d+)/i', $round, $m)) {
            return 'Тұрақты маусым, '.$m[1].'-тур';
        }

        return $round;
    }

    /** @return array<string, mixed> */
    private function seedData(): array
    {
        $file = $this->config()['data_file'] ?? database_path('data/kz_premier_league.php');

        return file_exists($file) ? require $file : ['meta' => [], 'standings' => [], 'fixtures' => []];
    }

    /** @param array<string, mixed> $item */
    private function normalizeApiFixture(array $item): array
    {
        $info = $item['fixture'] ?? [];
        $teams = $item['teams'] ?? [];
        $goals = $item['goals'] ?? [];
        $league = $item['league'] ?? [];
        $venue = $info['venue'] ?? [];
        $round = $league['round'] ?? '';

        return [
            'match_id' => $info['id'] ?? null,
            'round' => $round,
            'home_team' => $teams['home']['name'] ?? '',
            'away_team' => $teams['away']['name'] ?? '',
            'home_team_flag' => $teams['home']['logo'] ?? null,
            'away_team_flag' => $teams['away']['logo'] ?? null,
            'home_score' => $goals['home'],
            'away_score' => $goals['away'],
            'status' => $info['status']['short'] ?? 'NS',
            'minute' => $info['status']['elapsed'] ?? null,
            'kickoff_at' => isset($info['date']) ? date('Y-m-d H:i', strtotime($info['date'])) : null,
            'venue' => $venue['name'] ?? null,
            'competition' => trim(($league['name'] ?? 'ҚПЛ').($round ? ' · '.$this->translateRound($round) : '')),
        ];
    }

    /** @param array<int, array<string, mixed>> $items */
    private function normalizeApiTeams(array $items): array
    {
        $teams = [];

        foreach ($items as $item) {
            $team = $item['team'] ?? [];
            $venue = $item['venue'] ?? [];

            $teams[] = [
                'id' => $team['id'] ?? null,
                'name' => $team['name'] ?? '',
                'logo' => $team['logo'] ?? null,
                'founded' => $team['founded'] ?? null,
                'venue' => [
                    'id' => $venue['id'] ?? null,
                    'name' => $venue['name'] ?? null,
                    'address' => $venue['address'] ?? null,
                    'city' => $venue['city'] ?? null,
                    'capacity' => $venue['capacity'] ?? null,
                    'image' => $venue['image'] ?? null,
                ],
            ];
        }

        return $teams;
    }

    private function cacheTtl(): int
    {
        $meta = $this->seedData()['meta'];
        $start = Carbon::parse($meta['start_date'] ?? now()->startOfYear());
        $end = Carbon::parse($meta['end_date'] ?? now()->endOfYear())->addDay();

        if (now()->between($start, $end)) {
            return 300;
        }

        if (now()->lt($start)) {
            return 3600 * 6;
        }

        return 3600 * 24;
    }

    /** @param array<string, mixed> $fixture */
    private function isFinished(array $fixture): bool
    {
        $status = $fixture['status'] ?? 'NS';

        if (in_array($status, ['FT', 'AET', 'PEN'], true)) {
            return true;
        }

        if ($this->isLive($fixture)) {
            return false;
        }

        $kickoff = $fixture['kickoff_at'] ?? null;

        if (! $kickoff) {
            return false;
        }

        return Carbon::parse($kickoff)->addMinutes(105)->isPast();
    }

    /** @param array<string, mixed> $fixture */
    private function isLive(array $fixture): bool
    {
        return in_array($fixture['status'] ?? 'NS', ['LIVE', '1H', '2H', 'HT', 'ET', 'BT', 'P'], true);
    }
}
