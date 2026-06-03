<?php

namespace App\Services;

use App\Models\Fixture;
use App\Support\ApiFootballClient;
use App\Support\NationalTeamFlags;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MatchSliderService
{
    private const CACHE_KEY = 'match-slider.fixtures';

    /** @return array{fixtures: array<int, array<string, mixed>>, start_index: int} */
    public function getSliderData(): array
    {
        return Cache::remember(self::CACHE_KEY, 120, function () {
            $items = $this->collectFixtures();
            $items = $this->sortForSlider($items);
            $startIndex = $this->resolveStartIndex($items);

            return [
                'fixtures' => array_slice($items, 0, 40),
                'start_index' => $startIndex,
            ];
        });
    }

    /** @return array<int, array<string, mixed>> */
    private function collectFixtures(): array
    {
        $merged = [];

        foreach ($this->fromDatabase() as $item) {
            $merged[$this->mergeKey($item)] = $item;
        }

        foreach ($this->fromApi() as $item) {
            $merged[$this->mergeKey($item)] = $item;
        }

        return array_values($merged);
    }

    /** @return array<int, array<string, mixed>> */
    private function fromDatabase(): array
    {
        return Fixture::query()
            ->where('kickoff_at', '>=', now()->subDays(3))
            ->where('kickoff_at', '<=', now()->addDays(10))
            ->orderBy('kickoff_at')
            ->limit(50)
            ->get()
            ->map(fn (Fixture $f) => $this->normalizeModelFixture($f))
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function fromApi(): array
    {
        $apiKey = config('football.api_football_key');

        if (! $apiKey) {
            return [];
        }

        try {
            $from = now()->subDays(2)->format('Y-m-d');
            $to = now()->addDays(8)->format('Y-m-d');

            if (ApiFootballClient::isPaused()) {
                return [];
            }

            $response = ApiFootballClient::get(
                'https://v3.football.api-sports.io/fixtures',
                [
                    'from' => $from,
                    'to' => $to,
                ],
                25
            );

            if (! $response->successful()) {
                return [];
            }

            $items = [];

            foreach ($response->json('response') ?? [] as $row) {
                $items[] = $this->normalizeApiFixture($row);
            }

            return $items;
        } catch (\Throwable $e) {
            Log::warning('Match slider API fetch failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /** @return array<string, mixed> */
    private function normalizeModelFixture(Fixture $fixture): array
    {
        return [
            'id' => $fixture->external_id,
            'url' => route('fixtures.show', $fixture->external_id),
            'home_team' => $fixture->home_team,
            'away_team' => $fixture->away_team,
            'home_short' => $this->shortName($fixture->home_team),
            'away_short' => $this->shortName($fixture->away_team),
            'home_flag' => $fixture->home_team_flag ?: NationalTeamFlags::url($fixture->home_team),
            'away_flag' => $fixture->away_team_flag ?: NationalTeamFlags::url($fixture->away_team),
            'home_score' => $fixture->home_score,
            'away_score' => $fixture->away_score,
            'status' => $fixture->status ?? 'NS',
            'minute' => $fixture->minute,
            'kickoff_at' => $fixture->kickoff_at?->format('Y-m-d H:i:s'),
            'is_live' => $fixture->isLive(),
            'is_finished' => $fixture->isFinished(),
        ];
    }

    /** @param array<string, mixed> $item */
    private function normalizeApiFixture(array $item): array
    {
        $info = $item['fixture'] ?? [];
        $teams = $item['teams'] ?? [];
        $goals = $item['goals'] ?? [];
        $apiId = $info['id'] ?? null;
        $status = $info['status']['short'] ?? 'NS';
        $home = $teams['home']['name'] ?? '';
        $away = $teams['away']['name'] ?? '';

        $existing = $apiId
            ? Fixture::where('api_fixture_id', $apiId)->first()
            : null;

        $url = $existing
            ? route('fixtures.show', $existing->external_id)
            : route('fixtures.index');

        return [
            'id' => $existing?->external_id ?? $apiId,
            'url' => $url,
            'home_team' => $home,
            'away_team' => $away,
            'home_short' => $this->shortName($home),
            'away_short' => $this->shortName($away),
            'home_flag' => $teams['home']['logo'] ?? NationalTeamFlags::url($home),
            'away_flag' => $teams['away']['logo'] ?? NationalTeamFlags::url($away),
            'home_score' => $goals['home'],
            'away_score' => $goals['away'],
            'status' => $status,
            'minute' => $info['status']['elapsed'] ?? null,
            'kickoff_at' => isset($info['date']) ? date('Y-m-d H:i:s', strtotime($info['date'])) : null,
            'is_live' => in_array($status, ['LIVE', '1H', '2H', 'HT', 'ET', 'BT', 'P'], true),
            'is_finished' => in_array($status, ['FT', 'AET', 'PEN'], true),
        ];
    }

    /** @param array<string, mixed> $item */
    private function mergeKey(array $item): string
    {
        $kickoff = $item['kickoff_at'] ?? '';
        $home = mb_strtolower($item['home_team'] ?? '');
        $away = mb_strtolower($item['away_team'] ?? '');

        return md5($kickoff.'|'.$home.'|'.$away);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function sortForSlider(array $items): array
    {
        usort($items, function (array $a, array $b) {
            $at = strtotime($a['kickoff_at'] ?? '') ?: 0;
            $bt = strtotime($b['kickoff_at'] ?? '') ?: 0;

            return $at <=> $bt;
        });

        return array_map(fn (array $item) => $this->enrichPresentation($item), $items);
    }

    /** @param array<int, array<string, mixed>> $items */
    private function resolveStartIndex(array $items): int
    {
        if ($items === []) {
            return 0;
        }

        foreach ($items as $index => $item) {
            if ($item['is_live'] ?? false) {
                return max(0, $index);
            }
        }

        $now = now()->timestamp;

        foreach ($items as $index => $item) {
            $kickoff = strtotime($item['kickoff_at'] ?? '') ?: 0;

            if ($kickoff >= $now) {
                return max(0, $index - 1);
            }
        }

        return max(0, count($items) - 1);
    }

    /** @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function enrichPresentation(array $item): array
    {
        $kickoff = isset($item['kickoff_at']) ? Carbon::parse($item['kickoff_at']) : null;
        $isLive = (bool) ($item['is_live'] ?? false);
        $isFinished = (bool) ($item['is_finished'] ?? false);

        if ($isLive) {
            $minute = $item['minute'] ?? null;
            $item['status_class'] = 'live';
            $item['status_html'] = $minute
                ? '<span class="match-slider__timer">'.$minute."'</span> <span class=\"match-slider__live-label\">Өтуде</span>"
                : '<span class="match-slider__live-label">LIVE</span>';
            $item['status_bar_color'] = '#1d9a52';
            $item['status_bar_width'] = $this->liveProgressWidth((int) $minute);
        } elseif ($isFinished) {
            $item['status_class'] = 'finished';
            $item['status_html'] = $kickoff
                ? '<span class="match-slider__time">'.$kickoff->format('H:i').'</span>, <span class="match-slider__day">'.$this->dayLabel($kickoff).'</span>'
                : '<span class="match-slider__finished">Аяқталды</span>';
            $item['status_bar_color'] = '#ff6f00';
            $item['status_bar_width'] = '100%';
        } else {
            $item['status_class'] = 'scheduled';
            $item['status_html'] = $kickoff
                ? '<span class="match-slider__time">'.$kickoff->format('H:i').'</span>, <span class="match-slider__day">'.$this->dayLabel($kickoff).'</span>'
                : '<span class="match-slider__scheduled">—</span>';
            $item['status_bar_color'] = null;
            $item['status_bar_width'] = null;
        }

        $item['home_score_display'] = ($isFinished || $isLive) && $item['home_score'] !== null
            ? (string) $item['home_score']
            : '–';
        $item['away_score_display'] = ($isFinished || $isLive) && $item['away_score'] !== null
            ? (string) $item['away_score']
            : '–';
        $item['title'] = ($item['home_team'] ?? '').' — '.($item['away_team'] ?? '');

        return $item;
    }

    private function dayLabel(Carbon $date): string
    {
        if ($date->isToday()) {
            return 'бүгін';
        }

        if ($date->isYesterday()) {
            return 'кеше';
        }

        if ($date->isTomorrow()) {
            return 'ертең';
        }

        return $date->translatedFormat('d MMM');
    }

    private function liveProgressWidth(int $minute): string
    {
        $pct = min(100, max(8, (int) round(($minute / 90) * 100)));

        return $pct.'%';
    }

    private function shortName(?string $name): string
    {
        $name = trim((string) $name);

        if ($name === '') {
            return '—';
        }

        if (mb_strlen($name) <= 3) {
            return $name;
        }

        return mb_strtoupper(mb_substr($name, 0, 3));
    }
}
