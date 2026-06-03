<?php

namespace App\Console\Commands;

use App\Services\KzPremierLeagueService;
use Illuminate\Console\Command;

class SyncKzPremierLeagueCommand extends Command
{
    protected $signature = 'premier-liga:sync';

    protected $description = 'Синхронизация ҚПЛ: матчи, команды (API-Football)';

    public function handle(KzPremierLeagueService $service): int
    {
        if (! config('football.api_football_key')) {
            $this->warn('API_FOOTBALL_KEY не задан.');

            return self::FAILURE;
        }

        $ok = $service->syncFromApi();

        if ($ok) {
            $this->info('ҚПЛ синхронизирован (сезон '.$service->resolvedSeason().').');

            return self::SUCCESS;
        }

        $this->warn('API недоступен, используются резервные данные.');

        return self::SUCCESS;
    }
}
