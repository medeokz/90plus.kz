<?php

namespace App\Services;

use App\Models\Fixture;
use Illuminate\Support\Carbon;

class CompactMatchesService
{
    /**
     * @return array{live: array<int, array<string, mixed>>, upcoming: array<int, array<string, mixed>>}
     */
    public function getData(int $liveLimit = 6, int $upcomingLimit = 8): array
    {
        $now = now();

        $live = Fixture::query()
            ->whereIn('status', ['LIVE', '1H', '2H', 'HT', 'ET', 'BT', 'P'])
            ->orderBy('kickoff_at')
            ->limit($liveLimit)
            ->get()
            ->map(fn (Fixture $fixture): array => $this->mapFixture($fixture))
            ->values()
            ->all();

        $upcoming = Fixture::query()
            ->where('kickoff_at', '>=', $now->copy()->subMinutes(20))
            ->whereIn('status', ['NS', 'TBD', 'PST'])
            ->orderBy('kickoff_at')
            ->limit($upcomingLimit)
            ->get()
            ->map(fn (Fixture $fixture): array => $this->mapFixture($fixture))
            ->values()
            ->all();

        return [
            'live' => $live,
            'upcoming' => $upcoming,
        ];
    }

    /** @return array<string, mixed> */
    private function mapFixture(Fixture $fixture): array
    {
        $kickoff = $fixture->kickoff_at instanceof Carbon ? $fixture->kickoff_at : null;
        $isLive = $fixture->isLive();
        $status = $isLive
            ? ($fixture->minute ? $fixture->minute."'" : 'LIVE')
            : ($kickoff ? $kickoff->format('H:i') : $fixture->statusLabel());

        return [
            'url' => route('fixtures.show', $fixture->external_id),
            'competition' => (string) ($fixture->competition ?? ''),
            'home_team' => (string) $fixture->home_team,
            'away_team' => (string) $fixture->away_team,
            'home_score' => $fixture->home_score,
            'away_score' => $fixture->away_score,
            'status' => $status,
            'is_live' => $isLive,
            'kickoff_label' => $kickoff ? $kickoff->translatedFormat('d MMM, HH:mm') : null,
        ];
    }
}

