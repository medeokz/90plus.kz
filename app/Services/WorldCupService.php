<?php

namespace App\Services;

use App\Support\ApiFootballClient;
use App\Support\NationalTeamFlags;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WorldCupService
{
    private const CACHE_KEY = 'competition.world-cup-2026';

    /** @return array<string, mixed> */
    public function config(): array
    {
        return config('football.competitions.world-cup-2026', []);
    }

    /** @return array<string, mixed> */
    public function getPageData(string $tab = 'tournament'): array
    {
        $this->syncIfNeeded();

        $fixtures = $this->applyFixtureFlags($this->getFixtures());
        $groups = $this->getGroups($fixtures);
        $finished = count(array_filter($fixtures, fn (array $f) => $this->isFinished($f)));
        $seed = $this->seedData();
        $meta = $seed['meta'];

        return [
            'competition' => $this->config(),
            'tab' => $tab,
            'overview' => [
                'start_date' => $meta['start_date'],
                'end_date' => $meta['end_date'],
                'total_matches' => $meta['total_matches'],
                'played' => $finished,
                'stage' => $this->currentStage($fixtures),
                'source' => Cache::get(self::CACHE_KEY.'.source', 'seed'),
            ],
            'groups' => $groups,
            'third_place' => $this->getThirdPlaceTable($fixtures),
            'knockout' => $this->getKnockoutFixtures($fixtures),
            'schedule' => $this->filterFixtures($fixtures, 'schedule'),
            'results' => $this->filterFixtures($fixtures, 'results'),
        ];
    }

    public function syncFromApi(): bool
    {
        $config = $this->config();
        $apiKey = config('football.api_football_key');

        if (! $apiKey || ApiFootballClient::isPaused()) {
            return false;
        }

        try {
            $leagueId = $config['api_league_id'];
            $season = $config['api_season'];

            $fixturesResp = ApiFootballClient::get(
                'https://v3.football.api-sports.io/fixtures',
                [
                    'league' => $leagueId,
                    'season' => $season,
                ],
                30
            );

            $errors = $fixturesResp->json('errors') ?? [];
            $apiFixtures = $fixturesResp->json('response') ?? [];

            if (! empty($errors) || empty($apiFixtures)) {
                Cache::put(self::CACHE_KEY.'.source', 'seed', 3600);

                return false;
            }

            $normalized = [];

            foreach ($apiFixtures as $item) {
                $normalized[] = $this->normalizeApiFixture($item);
            }

            Cache::put(self::CACHE_KEY.'.fixtures', $normalized, $this->cacheTtl());
            Cache::put(self::CACHE_KEY.'.source', 'api', $this->cacheTtl());
            Cache::forget(self::CACHE_KEY.'.merged');

            $standingsResp = ApiFootballClient::get(
                'https://v3.football.api-sports.io/standings',
                [
                    'league' => $leagueId,
                    'season' => $season,
                ]
            );

            $standings = $standingsResp->json('response.0.league.standings') ?? [];

            if (! empty($standings)) {
                Cache::put(self::CACHE_KEY.'.standings', $this->normalizeApiStandings($standings), $this->cacheTtl());
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('World Cup API sync failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function syncIfNeeded(): void
    {
        $ttl = $this->cacheTtl();

        Cache::remember(self::CACHE_KEY.'.sync_lock', min($ttl, 300), function () {
            $this->syncFromApi();

            return true;
        });
    }

    /** @return array<int, array<string, mixed>> */
    private function getFixtures(): array
    {
        return Cache::remember(self::CACHE_KEY.'.merged', $this->cacheTtl(), function () {
            $apiFixtures = Cache::get(self::CACHE_KEY.'.fixtures');
            $seedFixtures = $this->buildSeedFixtures();

            if (is_array($apiFixtures) && ! empty($apiFixtures)) {
                return $apiFixtures;
            }

            return $seedFixtures;
        });
    }

    /** @param array<int, array<string, mixed>> $fixtures */
    private function getGroups(array $fixtures): array
    {
        $cachedStandings = Cache::get(self::CACHE_KEY.'.standings');
        $seed = $this->seedData();
        $groups = [];

        foreach ($seed['groups'] as $letter => $teams) {
            $standings = [];

            if (is_array($cachedStandings) && isset($cachedStandings[$letter])) {
                $standings = $this->applyStandingFlags($cachedStandings[$letter]);
            } else {
                $standings = $this->applyStandingFlags(
                    $this->buildGroupStandings($letter, $teams, $fixtures)
                );
            }

            $groups[$letter] = [
                'letter' => $letter,
                'standings' => $standings,
                'fixtures' => array_values(array_filter(
                    $fixtures,
                    fn (array $f) => ($f['group'] ?? null) === $letter
                )),
            ];
        }

        return $groups;
    }

    /** @param array<int, array<string, mixed>> $fixtures */
    private function getThirdPlaceTable(array $fixtures): array
    {
        $teams = $this->seedData()['third_place_teams'];
        $rows = [];

        foreach ($teams as $index => $team) {
            $stats = $this->teamStatsFromFixtures($team, $fixtures);
            $rows[] = array_merge(['rank' => $index + 1, 'team' => $team, 'logo' => null], $stats);
        }

        usort($rows, fn ($a, $b) => ($b['points'] <=> $a['points']) ?: ($b['gd'] <=> $a['gd']));

        foreach ($rows as $i => &$row) {
            $row['rank'] = $i + 1;
        }

        return $this->applyStandingFlags($rows);
    }

    /** @param array<int, array<string, mixed>> $fixtures */
    private function getKnockoutFixtures(array $fixtures): array
    {
        $knockout = array_values(array_filter(
            $fixtures,
            fn (array $f) => empty($f['group'])
        ));

        if (! empty($knockout)) {
            return $this->groupKnockoutByRound($knockout);
        }

        $seedKnockout = $this->buildKnockoutFromSeed();

        return $this->groupKnockoutByRound($seedKnockout);
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

    /** @param array<int, array<string, mixed>> $fixtures */
    private function currentStage(array $fixtures): string
    {
        $live = array_filter($fixtures, fn (array $f) => $this->isLive($f));

        if (! empty($live)) {
            return 'Ойын өтіп жатыр';
        }

        $upcoming = array_filter($fixtures, fn (array $f) => ! $this->isFinished($f));

        if (empty($upcoming)) {
            return 'Турнир аяқталды';
        }

        usort($upcoming, fn ($a, $b) => strtotime($a['kickoff_at']) <=> strtotime($b['kickoff_at']));
        $next = reset($upcoming);

        if (! empty($next['round'])) {
            return $next['round'];
        }

        if (! empty($next['group'])) {
            return $next['group'].' тобы';
        }

        return 'Топтық саты';
    }

    /** @return array<string, mixed> */
    private function seedData(): array
    {
        $file = $this->config()['data_file'] ?? database_path('data/world_cup_2026.php');

        return file_exists($file) ? require $file : ['meta' => [], 'groups' => [], 'group_fixtures' => [], 'knockout' => [], 'third_place_teams' => []];
    }

    /** @return array<int, array<string, mixed>> */
    private function buildSeedFixtures(): array
    {
        $seed = $this->seedData();
        $fixtures = [];
        $id = 1;

        foreach ($seed['group_fixtures'] as $row) {
            $fixtures[] = [
                'match_id' => $id,
                'group' => $row['group'],
                'round' => 'Group '.$row['group'],
                'home_team' => $row['home'],
                'away_team' => $row['away'],
                'home_team_flag' => NationalTeamFlags::url($row['home']),
                'away_team_flag' => NationalTeamFlags::url($row['away']),
                'home_score' => null,
                'away_score' => null,
                'status' => 'NS',
                'kickoff_at' => $row['at'],
                'competition' => 'Әлем чемпионаты 2026 · '.$row['group'].' тобы',
            ];
            $id++;
        }

        foreach ($seed['knockout'] as $row) {
            $fixtures[] = [
                'match_id' => $id,
                'group' => null,
                'round' => $row['round'],
                'home_team' => $row['home'],
                'away_team' => $row['away'],
                'home_team_flag' => NationalTeamFlags::url($row['home']),
                'away_team_flag' => NationalTeamFlags::url($row['away']),
                'home_score' => null,
                'away_score' => null,
                'status' => 'NS',
                'kickoff_at' => $row['at'],
                'competition' => 'Әлем чемпионаты 2026 · '.$row['round'],
            ];
            $id++;
        }

        return $fixtures;
    }

    /** @return array<int, array<string, mixed>> */
    private function buildKnockoutFromSeed(): array
    {
        $seed = $this->seedData();
        $fixtures = [];
        $id = count($seed['group_fixtures']) + 1;

        foreach ($seed['knockout'] as $row) {
            $fixtures[] = [
                'match_id' => $id,
                'group' => null,
                'round' => $row['round'],
                'home_team' => $row['home'],
                'away_team' => $row['away'],
                'home_team_flag' => NationalTeamFlags::url($row['home']),
                'away_team_flag' => NationalTeamFlags::url($row['away']),
                'home_score' => null,
                'away_score' => null,
                'status' => 'NS',
                'kickoff_at' => $row['at'],
                'competition' => 'Әлем чемпионаты 2026 · '.$row['round'],
            ];
            $id++;
        }

        return $fixtures;
    }

    /** @param array<int, array<string, mixed>> $fixtures */
    private function buildGroupStandings(string $letter, array $teams, array $fixtures): array
    {
        $rows = [];

        foreach ($teams as $index => $team) {
            $stats = $this->teamStatsFromFixtures($team, $fixtures, $letter);
            $rows[] = array_merge([
                'rank' => $index + 1,
                'team' => $team,
                'logo' => null,
            ], $stats);
        }

        usort($rows, fn ($a, $b) => ($b['points'] <=> $a['points']) ?: ($b['gd'] <=> $a['gd']));

        foreach ($rows as $i => &$row) {
            $row['rank'] = $i + 1;
        }

        return $rows;
    }

    /** @param array<int, array<string, mixed>> $fixtures */
    private function teamStatsFromFixtures(string $team, array $fixtures, ?string $group = null): array
    {
        $stats = ['played' => 0, 'won' => 0, 'drawn' => 0, 'lost' => 0, 'gf' => 0, 'ga' => 0, 'gd' => 0, 'points' => 0];

        foreach ($fixtures as $fixture) {
            if ($group !== null && ($fixture['group'] ?? null) !== $group) {
                continue;
            }

            if (! $this->isFinished($fixture)) {
                continue;
            }

            $home = $fixture['home_team'] ?? '';
            $away = $fixture['away_team'] ?? '';
            $hs = (int) ($fixture['home_score'] ?? 0);
            $as = (int) ($fixture['away_score'] ?? 0);

            if ($home !== $team && $away !== $team) {
                continue;
            }

            $stats['played']++;
            $stats['gf'] += $home === $team ? $hs : $as;
            $stats['ga'] += $home === $team ? $as : $hs;

            if ($hs === $as) {
                $stats['drawn']++;
                $stats['points'] += 1;
            } elseif (($home === $team && $hs > $as) || ($away === $team && $as > $hs)) {
                $stats['won']++;
                $stats['points'] += 3;
            } else {
                $stats['lost']++;
            }
        }

        $stats['gd'] = $stats['gf'] - $stats['ga'];

        return $stats;
    }

    /** @param array<string, mixed> $item */
    private function normalizeApiFixture(array $item): array
    {
        $info = $item['fixture'] ?? [];
        $teams = $item['teams'] ?? [];
        $goals = $item['goals'] ?? [];
        $league = $item['league'] ?? [];
        $apiId = $info['id'] ?? null;
        $round = $league['round'] ?? '';
        $group = null;

        if (preg_match('/Group\s+([A-L])/i', $round, $m)) {
            $group = strtoupper($m[1]);
        }

        return [
            'match_id' => $apiId,
            'group' => $group,
            'round' => $round,
            'home_team' => $teams['home']['name'] ?? '',
            'away_team' => $teams['away']['name'] ?? '',
            'home_team_flag' => $teams['home']['logo'] ?? NationalTeamFlags::url($teams['home']['name'] ?? ''),
            'away_team_flag' => $teams['away']['logo'] ?? NationalTeamFlags::url($teams['away']['name'] ?? ''),
            'home_score' => $goals['home'],
            'away_score' => $goals['away'],
            'status' => $info['status']['short'] ?? 'NS',
            'minute' => $info['status']['elapsed'] ?? null,
            'kickoff_at' => isset($info['date']) ? date('Y-m-d H:i', strtotime($info['date'])) : null,
            'competition' => trim(($league['name'] ?? 'World Cup').($round ? ' · '.$round : '')),
        ];
    }

    /** @param array<int, array<int, array<string, mixed>>> $standingsGroups */
    private function normalizeApiStandings(array $standingsGroups): array
    {
        $result = [];

        foreach ($standingsGroups as $groupRows) {
            if (empty($groupRows[0]['group'])) {
                continue;
            }

            $letter = strtoupper(str_replace('Group ', '', $groupRows[0]['group']));
            $result[$letter] = array_map(fn (array $row) => [
                'rank' => $row['rank'],
                'team' => $row['team']['name'],
                'logo' => $row['team']['logo'] ?? NationalTeamFlags::url($row['team']['name'] ?? ''),
                'played' => $row['all']['played'],
                'won' => $row['all']['win'],
                'drawn' => $row['all']['draw'],
                'lost' => $row['all']['lose'],
                'gf' => $row['all']['goals']['for'],
                'ga' => $row['all']['goals']['against'],
                'gd' => $row['goalsDiff'],
                'points' => $row['points'],
            ], $groupRows);
        }

        return $result;
    }

    private function extractGroupLetter(?string $group): ?string
    {
        if ($group === null || $group === '') {
            return null;
        }

        if (preg_match('/GROUP_([A-L])/i', $group, $m)) {
            return strtoupper($m[1]);
        }

        if (preg_match('/Group\s+([A-L])/i', $group, $m)) {
            return strtoupper($m[1]);
        }

        return null;
    }

    /** @param array<int, array<string, mixed>> $fixtures */
    private function groupKnockoutByRound(array $fixtures): array
    {
        $order = [
            'Round of 32' => 1,
            'Round of 16' => 2,
            'Quarter-finals' => 3,
            'Semi-finals' => 4,
            '3rd Place' => 5,
            'Final' => 6,
        ];

        usort($fixtures, function ($a, $b) use ($order) {
            $ra = $order[$a['round'] ?? ''] ?? 99;
            $rb = $order[$b['round'] ?? ''] ?? 99;

            return $ra === $rb
                ? strtotime($a['kickoff_at']) <=> strtotime($b['kickoff_at'])
                : $ra <=> $rb;
        });

        $grouped = [];
        foreach ($fixtures as $fixture) {
            $round = $fixture['round'] ?? 'Play-offs';
            $grouped[$round][] = $fixture;
        }

        return $grouped;
    }

    private function cacheTtl(): int
    {
        $start = Carbon::parse($this->seedData()['meta']['start_date'] ?? '2026-06-11');
        $end = Carbon::parse($this->seedData()['meta']['end_date'] ?? '2026-07-19')->addDay();

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
        return in_array($fixture['status'] ?? 'NS', ['FT', 'AET', 'PEN'], true);
    }

    /** @param array<string, mixed> $fixture */
    private function isLive(array $fixture): bool
    {
        return in_array($fixture['status'] ?? 'NS', ['LIVE', '1H', '2H', 'HT', 'ET', 'BT', 'P'], true);
    }

    /** @param array<int, array<string, mixed>> $fixtures */
    private function applyFixtureFlags(array $fixtures): array
    {
        return array_map(function (array $fixture) {
            if (empty($fixture['home_team_flag'])) {
                $fixture['home_team_flag'] = NationalTeamFlags::url($fixture['home_team'] ?? '');
            }

            if (empty($fixture['away_team_flag'])) {
                $fixture['away_team_flag'] = NationalTeamFlags::url($fixture['away_team'] ?? '');
            }

            return $fixture;
        }, $fixtures);
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function applyStandingFlags(array $rows): array
    {
        return array_map(function (array $row) {
            if (empty($row['logo'])) {
                $row['logo'] = NationalTeamFlags::url($row['team'] ?? '');
            }

            return $row;
        }, $rows);
    }
}
