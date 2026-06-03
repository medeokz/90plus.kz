<?php

namespace App\Console\Commands;

use App\Models\Fixture;
use App\Services\FixtureStatsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SyncFixturesCommand extends Command
{
    protected $signature = 'fixtures:sync
        {api_fixture_id? : ID матча в API-Football}
        {--external-id= : Внешний ID для URL /games/{id}}
        {--live : Импортировать все live-матчи}
        {--tracked : Обновить отслеживаемые матчи из базы}';

    protected $description = 'Синхронизировать матч(и) с API-Football';

    public function handle(FixtureStatsService $service): int
    {
        if (! config('football.api_football_key')) {
            $this->error('Добавьте API_FOOTBALL_KEY в .env');

            return self::FAILURE;
        }

        if ($this->option('live')) {
            return $this->syncLive($service);
        }

        if ($this->option('tracked')) {
            return $this->syncTracked($service);
        }

        $apiId = (int) $this->argument('api_fixture_id');

        if ($apiId <= 0) {
            $this->error('Укажите api_fixture_id или флаг --live / --tracked');

            return self::FAILURE;
        }

        $externalId = $this->option('external-id') ? (int) $this->option('external-id') : $apiId;

        $fixture = $service->syncFromApi($apiId, $externalId);

        if (! $fixture) {
            $this->error("Матч API #{$apiId} не найден.");

            return self::FAILURE;
        }

        $this->info("Синхронизирован: {$fixture->home_team} {$fixture->home_score}:{$fixture->away_score} {$fixture->away_team}");
        $this->line('Страница: '.route('fixtures.show', $fixture->external_id));

        return self::SUCCESS;
    }

    private function syncLive(FixtureStatsService $service): int
    {
        $response = Http::timeout(20)
            ->withHeaders(['x-apisports-key' => config('football.api_football_key')])
            ->get('https://v3.football.api-sports.io/fixtures', ['live' => 'all']);

        $items = $response->json('response') ?? [];
        $count = 0;

        foreach ($items as $item) {
            $apiId = $item['fixture']['id'] ?? null;

            if (! $apiId) {
                continue;
            }

            $existing = Fixture::where('api_fixture_id', $apiId)->first();
            $externalId = $existing?->external_id ?? $apiId;

            $fixture = $service->syncFromApi($apiId, $externalId);

            if ($fixture) {
                $count++;
                $this->line("  ✓ {$fixture->home_team} {$fixture->home_score}:{$fixture->away_score} {$fixture->away_team}");
            }
        }

        $this->info("Live-матчей синхронизировано: {$count}");

        return self::SUCCESS;
    }

    private function syncTracked(FixtureStatsService $service): int
    {
        $service->refreshForIndex();
        $this->info('Отслеживаемые матчи обновлены.');

        return self::SUCCESS;
    }
}
