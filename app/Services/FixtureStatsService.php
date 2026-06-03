<?php

namespace App\Services;

use App\Models\Fixture;
use App\Support\ApiFootballClient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FixtureStatsService
{
    private const STAT_LABELS = [
        'Expected Goals' => 'Күтілетін голдар (xG)',
        'Total Shots' => 'Соққılar',
        'Shots on Goal' => 'Қақпаға соққылар',
        'Shots off Goal' => 'Қақпадан тыс соққылар',
        'Blocked Shots' => 'Блокталған соққылар',
        'Shots insidebox' => 'Алаң ішіндегі соққылар',
        'Shots outsidebox' => 'Алаң сыртындағы соққылар',
        'Goalkeeper Saves' => 'Қақпашы сейвтері',
        'Ball Possession' => 'Доп алу %',
        'Corner Kicks' => 'Бұрыштама добы',
        'Fouls' => 'Фолдар',
        'Offsides' => 'Офсайдтар',
        'Yellow Cards' => 'Сары карточкалар',
        'Red Cards' => 'Қызыл карточкалар',
        'Total passes' => 'Пас берулер',
        'Passes accurate' => 'Дәл пас берулер',
        'Passes %' => 'Пас дәлдігі %',
        'Free Kicks' => 'Ерkin соққылар',
        'Throw-ins' => 'Ауттар',
        'Clearances' => 'Алаңнан тазарту',
        'Big Chances' => 'Гол мүмкіндіктері',
        'Tackles' => 'Доп алулар',
    ];

    public function findOrFetch(int $externalId): ?Fixture
    {
        $fixture = Fixture::where('external_id', $externalId)->first();

        if (! $fixture) {
            return null;
        }

        $this->refreshFromApiIfNeeded($fixture);

        return $fixture->fresh();
    }

    /** Импорт или обновление матча по ID API-Football. */
    public function syncFromApi(int $apiFixtureId, ?int $externalId = null): ?Fixture
    {
        $data = $this->fetchFixtureBundle($apiFixtureId);

        if ($data === null) {
            return null;
        }

        $externalId ??= $apiFixtureId;

        $fixture = Fixture::firstOrNew(['external_id' => $externalId]);
        $fixture->api_fixture_id = $apiFixtureId;

        $this->applyApiData(
            $fixture,
            $data['fixture'],
            $data['statistics'],
            $data['events'],
            $data['lineups'],
        );

        $fixture->save();
        Cache::forget($this->cacheKey($apiFixtureId));

        return $fixture;
    }

    public function refreshFromApiIfNeeded(Fixture $fixture, bool $force = false): void
    {
        if (! $fixture->api_fixture_id || ! config('football.api_football_key')) {
            return;
        }

        $apiId = $fixture->api_fixture_id;

        if ($force) {
            Cache::forget($this->cacheKey($apiId));
        }

        $ttl = $fixture->isLive() ? 20 : ($force ? 30 : 300);

        Cache::remember($this->cacheKey($apiId), $ttl, function () use ($fixture, $apiId) {
            $data = $this->fetchFixtureBundle($apiId);

            if ($data !== null) {
                $this->applyApiData(
                    $fixture,
                    $data['fixture'],
                    $data['statistics'],
                    $data['events'],
                    $data['lineups'],
                );
                $fixture->save();
            }

            return true;
        });
    }

    /** Обновить матчи для списка /games перед отображением. */
    public function refreshForIndex(bool $aggressive = false): void
    {
        if (! config('football.api_football_key')) {
            $this->normalizeFinishedFixtures($this->indexQuery()->get());

            return;
        }

        if ($aggressive) {
            $this->syncLiveIfNeeded();
        }

        $fixtures = $this->indexQuery()->get();
        $maxRefreshes = (int) config('football.api_football_index_refresh_max', 3);
        $refreshed = 0;

        $sorted = $fixtures->sortByDesc(fn (Fixture $f) => ($f->isLive() ? 100 : 0)
            + ($f->kickoff_at && $f->kickoff_at->isFuture() && $f->kickoff_at->lte(now()->addHours(6)) ? 50 : 0));

        foreach ($sorted as $fixture) {
            if ($refreshed >= $maxRefreshes || ApiFootballClient::isPaused()) {
                break;
            }

            if ($fixture->api_fixture_id) {
                $shouldForce = $aggressive && (
                    $fixture->isLive()
                    || ($fixture->kickoff_at && $fixture->kickoff_at->between(now()->subHours(12), now()->addHours(6)))
                );

                $this->refreshFromApiIfNeeded($fixture, $shouldForce);
                $refreshed++;
            }
        }

        $this->normalizeFinishedFixtures($this->indexQuery()->get());
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, Fixture> */
    public function indexFixtures()
    {
        return $this->indexQuery()->get();
    }

    /** @return array<int, array<string, mixed>> */
    public function indexPayload(): array
    {
        return $this->indexFixtures()
            ->map(fn (Fixture $f) => $this->fixturePayload($f))
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    public function fixturePayload(Fixture $f): array
    {
        return [
            'external_id' => $f->external_id,
            'url' => route('fixtures.show', $f->external_id),
            'competition' => $f->competition,
            'kickoff_at' => $f->kickoff_at?->format('d.m, H:i'),
            'home_team' => $f->home_team,
            'away_team' => $f->away_team,
            'home_score' => $f->home_score,
            'away_score' => $f->away_score,
            'status' => $f->status,
            'status_label' => $f->statusLabel(),
            'is_live' => $f->isLive(),
        ];
    }

    /** @return Builder<Fixture> */
    private function indexQuery(): Builder
    {
        return Fixture::query()
            ->where(function ($query) {
                $query->where('external_id', '<', 742000)
                    ->orWhere('external_id', '>=', 743000);
            })
            ->where('competition', 'not like', 'Әлем чемпионаты%')
            ->orderByDesc('kickoff_at')
            ->limit(30);
    }

    public function syncLiveIfNeeded(): void
    {
        $apiKey = config('football.api_football_key');

        if (! $apiKey) {
            return;
        }

        Cache::remember('fixtures.live_sync_lock', 45, function () {
            if (ApiFootballClient::isPaused()) {
                return true;
            }

            try {
                $response = ApiFootballClient::get(
                    'https://v3.football.api-sports.io/fixtures',
                    ['live' => 'all']
                );

                if (! $response->successful()) {
                    return true;
                }

                foreach ($response->json('response') ?? [] as $item) {
                    $apiId = $item['fixture']['id'] ?? null;

                    if (! $apiId) {
                        continue;
                    }

                    $existing = Fixture::where('api_fixture_id', $apiId)->first();
                    $externalId = $existing?->external_id ?? $apiId;

                    Cache::forget($this->cacheKey($apiId));
                    $this->syncFromApi($apiId, $externalId);
                }
            } catch (\Throwable $e) {
                Log::warning('Live fixtures sync failed', ['error' => $e->getMessage()]);
            }

            return true;
        });
    }

    /** @param iterable<Fixture> $fixtures */
    private function normalizeFinishedFixtures(iterable $fixtures): void
    {
        foreach ($fixtures as $fixture) {
            if ($fixture->isFinished() || $fixture->isLive()) {
                continue;
            }

            if (! $fixture->kickoff_at) {
                continue;
            }

            if ($fixture->kickoff_at->copy()->addMinutes(105)->isPast()) {
                $fixture->status = 'FT';
                $fixture->save();
            }
        }
    }

    /** @return array{fixture: array, statistics: array, events: array, lineups: array}|null */
    private function fetchFixtureBundle(int $apiFixtureId): ?array
    {
        if (ApiFootballClient::isPaused()) {
            return null;
        }

        try {
            $fixtureResp = ApiFootballClient::get(
                'https://v3.football.api-sports.io/fixtures',
                ['id' => $apiFixtureId]
            );

            if (! $fixtureResp->successful()) {
                throw new \RuntimeException('HTTP '.$fixtureResp->status());
            }

            $fixture = $fixtureResp->json('response.0');

            if ($fixture === null) {
                return null;
            }

            $status = $fixture['fixture']['status']['short'] ?? 'NS';
            $needsDetail = in_array($status, ['1H', 'HT', '2H', 'ET', 'BT', 'P', 'LIVE', 'FT', 'AET', 'PEN'], true);

            $emptyStats = ['full' => [], 'first_half' => [], 'second_half' => []];

            if (! $needsDetail) {
                return [
                    'fixture' => $fixture,
                    'statistics' => $emptyStats,
                    'events' => [],
                    'lineups' => [],
                ];
            }

            $stats = $this->fetchAllStatistics($apiFixtureId, $status);

            $events = ApiFootballClient::get(
                'https://v3.football.api-sports.io/fixtures/events',
                ['fixture' => $apiFixtureId]
            )->json('response') ?? [];

            $lineups = ApiFootballClient::get(
                'https://v3.football.api-sports.io/fixtures/lineups',
                ['fixture' => $apiFixtureId]
            )->json('response') ?? [];

            return [
                'fixture' => $fixture,
                'statistics' => $stats,
                'events' => $events,
                'lineups' => $lineups,
            ];
        } catch (\Throwable $e) {
            if (! ApiFootballClient::isPaused()) {
                Log::warning('Fixture API fetch failed', ['api_id' => $apiFixtureId, 'error' => $e->getMessage()]);
            }

            return null;
        }
    }

    /** @return array{full: array, first_half: array, second_half: array} */
    private function fetchAllStatistics(int $apiFixtureId, string $status): array
    {
        $fetch = fn (?string $half = null) => ApiFootballClient::get(
            'https://v3.football.api-sports.io/fixtures/statistics',
            array_filter([
                'fixture' => $apiFixtureId,
                'half' => $half,
            ])
        )->json('response') ?? [];

        $full = $fetch();
        $firstHalf = [];
        $secondHalf = [];

        if (in_array($status, ['HT', '2H', 'FT', 'AET', 'PEN'], true)) {
            $firstHalf = $fetch('true');

            if ($this->statisticsEmpty($firstHalf) || $this->statisticsSame($full, $firstHalf)) {
                $firstHalf = [];
            }
        }

        if (in_array($status, ['FT', 'AET', 'PEN'], true)) {
            $secondHalf = $fetch('false');

            if ($this->statisticsEmpty($secondHalf) || $this->statisticsSame($full, $secondHalf)) {
                $secondHalf = [];
            }
        }

        return [
            'full' => $full,
            'first_half' => $firstHalf,
            'second_half' => $secondHalf,
        ];
    }

    private function statisticsEmpty(array $stats): bool
    {
        if ($stats === []) {
            return true;
        }

        foreach ($stats as $teamStats) {
            foreach ($teamStats['statistics'] ?? [] as $stat) {
                $value = $stat['value'] ?? null;
                if ($value !== null && $value !== '' && $value !== '0' && $value !== 0) {
                    return false;
                }
            }
        }

        return true;
    }

    private function statisticsSame(array $left, array $right): bool
    {
        return json_encode($left) === json_encode($right);
    }

    private function applyApiData(Fixture $fixture, array $data, array $stats, array $events, array $lineups): void
    {
        $info = $data['fixture'] ?? [];
        $teams = $data['teams'] ?? [];
        $goals = $data['goals'] ?? [];
        $league = $data['league'] ?? [];

        $homeId = $teams['home']['id'] ?? null;
        $awayId = $teams['away']['id'] ?? null;

        $fixture->home_score = (int) ($goals['home'] ?? 0);
        $fixture->away_score = (int) ($goals['away'] ?? 0);
        $fixture->status = $info['status']['short'] ?? 'NS';
        $fixture->minute = $info['status']['elapsed'] ?? null;
        $fixture->competition = trim(($league['name'] ?? '').($league['round'] ? ' · '.$league['round'] : ''));
        $fixture->venue = $info['venue']['name'] ?? null;
        $fixture->city = $info['venue']['city'] ?? null;
        $fixture->home_team = $teams['home']['name'] ?? $fixture->home_team;
        $fixture->away_team = $teams['away']['name'] ?? $fixture->away_team;
        $fixture->home_team_flag = $teams['home']['logo'] ?? $fixture->home_team_flag;
        $fixture->away_team_flag = $teams['away']['logo'] ?? $fixture->away_team_flag;

        if (! empty($info['date'])) {
            $fixture->kickoff_at = date('Y-m-d H:i:s', strtotime($info['date']));
        }

        if (! empty($events)) {
            $fixture->events = collect($events)->map(function ($e) use ($homeId) {
                $teamId = $e['team']['id'] ?? null;
                $type = strtolower($e['type'] ?? '');
                $detail = strtolower($e['detail'] ?? '');

                return [
                    'minute' => $e['time']['elapsed'] ?? null,
                    'extra' => $e['time']['extra'] ?? null,
                    'type' => $type === 'goal' ? 'goal' : ($type === 'card' ? 'card' : 'event'),
                    'detail' => $e['detail'] ?? '',
                    'team' => $teamId === $homeId ? 'home' : 'away',
                    'player' => $e['player']['name'] ?? '',
                    'assist' => $e['assist']['name'] ?? null,
                    'icon' => $this->eventIcon($type, $detail),
                ];
            })->values()->all();
        }

        if (! empty($lineups)) {
            $mapped = ['home' => null, 'away' => null];

            foreach ($lineups as $l) {
                $teamId = $l['team']['id'] ?? null;
                $side = $teamId === $homeId ? 'home' : 'away';

                $mapped[$side] = [
                    'coach' => $l['coach']['name'] ?? null,
                    'formation' => $l['formation'] ?? null,
                    'starting' => collect($l['startXI'] ?? [])->map(fn ($p) => [
                        'number' => $p['player']['number'] ?? null,
                        'name' => $p['player']['name'] ?? '',
                        'photo' => $p['player']['photo'] ?? null,
                    ])->values()->all(),
                    'subs' => collect($l['substitutes'] ?? [])->map(fn ($p) => [
                        'number' => $p['player']['number'] ?? null,
                        'name' => $p['player']['name'] ?? '',
                        'photo' => $p['player']['photo'] ?? null,
                    ])->values()->all(),
                ];
            }

            $fixture->lineups = $mapped;
        }

        if (! empty($stats)) {
            $fixture->statistics = [
                'periods' => $this->buildStatisticsPeriods(
                    $fixture,
                    $stats,
                    $homeId,
                    $fixture->status ?? 'NS',
                ),
            ];
        }
    }

    /** @param array{full: array, first_half: array, second_half: array} $stats */
    private function buildStatisticsPeriods(Fixture $fixture, array $stats, ?int $homeId, string $status): array
    {
        $existing = $fixture->statistics['periods'] ?? [];
        $periods = [];

        $fullRows = $this->mapStatistics($stats['full'] ?? [], $homeId);
        if (! empty($fullRows)) {
            $periods['full'] = $fullRows;
        }

        $firstHalfRows = $this->mapStatistics($stats['first_half'] ?? [], $homeId);
        if (! empty($firstHalfRows)) {
            $periods['1h'] = $firstHalfRows;
        }

        $secondHalfRows = $this->mapStatistics($stats['second_half'] ?? [], $homeId);
        if (! empty($secondHalfRows)) {
            $periods['2h'] = $secondHalfRows;
        }

        if (empty($periods['1h']) && in_array($status, ['HT', '2H', 'FT', 'AET', 'PEN'], true)) {
            $snapshot = ! empty($periods['full']) ? $periods['full'] : $this->mapStatistics($stats['full'] ?? [], $homeId);

            if ($status === 'HT' && ! empty($snapshot)) {
                $periods['1h'] = $snapshot;
            } elseif (! empty($existing['1h'])) {
                $periods['1h'] = $existing['1h'];
            }
        }

        if (empty($periods['2h']) && ! empty($periods['full']) && ! empty($periods['1h'])) {
            $computed = $this->subtractStatistics($periods['full'], $periods['1h']);
            if (! empty($computed)) {
                $periods['2h'] = $computed;
            }
        }

        foreach (['1h', '2h'] as $half) {
            if (empty($periods[$half]) && ! empty($existing[$half])) {
                $periods[$half] = $existing[$half];
            }
        }

        if (empty($periods['full']) && ! empty($existing['full'])) {
            $periods['full'] = $existing['full'];
        }

        return $periods;
    }

    /** @param array<int, array<string, mixed>> $full @param array<int, array<string, mixed>> $firstHalf */
    private function subtractStatistics(array $full, array $firstHalf): array
    {
        $firstByLabel = collect($firstHalf)->keyBy('label');
        $rows = [];

        foreach ($full as $row) {
            if (! empty($row['percent'])) {
                continue;
            }

            $first = $firstByLabel->get($row['label']);
            if ($first === null) {
                continue;
            }

            $home = max(0, (float) $row['home'] - (float) $first['home']);
            $away = max(0, (float) $row['away'] - (float) $first['away']);

            if ($home === 0.0 && $away === 0.0) {
                continue;
            }

            $rows[] = [
                'label' => $row['label'],
                'home' => fmod($home, 1.0) !== 0.0 ? round($home, 2) : (int) $home,
                'away' => fmod($away, 1.0) !== 0.0 ? round($away, 2) : (int) $away,
                'percent' => false,
            ];
        }

        return $rows;
    }

    /** @param  array<int, array<string, mixed>>  $stats */
    private function mapStatistics(array $stats, ?int $homeId): array
    {
        $homeStats = [];
        $awayStats = [];

        foreach ($stats as $teamStats) {
            $bucket = ($teamStats['team']['id'] ?? null) === $homeId ? 'home' : 'away';
            foreach ($teamStats['statistics'] ?? [] as $stat) {
                if ($bucket === 'home') {
                    $homeStats[$stat['type']] = $this->normalizeStatValue($stat['value']);
                } else {
                    $awayStats[$stat['type']] = $this->normalizeStatValue($stat['value']);
                }
            }
        }

        $rows = [];
        $types = array_unique(array_merge(array_keys($homeStats), array_keys($awayStats)));

        foreach ($types as $type) {
            $home = $homeStats[$type] ?? 0;
            $away = $awayStats[$type] ?? 0;

            if ($home === 0 && $away === 0) {
                continue;
            }

            $rows[] = [
                'label' => self::STAT_LABELS[$type] ?? $type,
                'home' => $home,
                'away' => $away,
                'percent' => str_contains($type, '%') || $type === 'Ball Possession',
            ];
        }

        return $rows;
    }

    private function normalizeStatValue(mixed $value): float|int
    {
        if (is_string($value) && str_ends_with($value, '%')) {
            return (int) rtrim($value, '%');
        }

        if (is_numeric($value)) {
            return str_contains((string) $value, '.') ? (float) $value : (int) $value;
        }

        return 0;
    }

    private function eventIcon(string $type, string $detail): string
    {
        if ($type === 'goal') {
            return '⚽';
        }

        if ($type === 'card') {
            return str_contains($detail, 'red') ? '🟥' : '🟨';
        }

        if ($type === 'subst') {
            return '🔄';
        }

        return '•';
    }

    private function cacheKey(int $apiFixtureId): string
    {
        return 'fixture.api.sync.'.$apiFixtureId;
    }
}
