<?php

namespace App\Console\Commands;

use App\Services\LeagueStandingsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class FetchStandingsCommand extends Command
{
    protected $signature = 'standings:fetch';

    protected $description = 'Обновить турнирные таблицы из API-Football';

    public function handle(LeagueStandingsService $service): int
    {
        if (! config('football.api_football_key')) {
            $this->error('Добавьте API_FOOTBALL_KEY в .env');

            return self::FAILURE;
        }

        $leagues = config('football.leagues', []);

        foreach ($leagues as $league) {
            Cache::forget('flashscore.standings.'.$league['key']);
            $this->line("Fetching {$league['name']}...");
        }

        $updated = $service->syncAll();

        foreach ($leagues as $league) {
            $count = count(Cache::get('league.standings.'.$league['key'], []));
            if ($count > 0) {
                $this->info("  ✓ {$league['key']}: {$count} teams");
            } else {
                $this->warn("  ✗ {$league['key']}: no data");
            }
        }

        $this->info("Done. Updated {$updated} of ".count($leagues).' leagues.');

        return self::SUCCESS;
    }
}
