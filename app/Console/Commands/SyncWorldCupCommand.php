<?php

namespace App\Console\Commands;

use App\Models\Fixture;
use App\Services\WorldCupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SyncWorldCupCommand extends Command
{
    protected $signature = 'world-cup:sync';

    protected $description = 'Синхронизировать данные Әлем чемпионаты 2026 с API-Football';

    public function handle(WorldCupService $service): int
    {
        Cache::forget('competition.world-cup-2026.merged');
        Cache::forget('competition.world-cup-2026.sync_lock');

        $removed = Fixture::query()
            ->whereBetween('external_id', [742000, 742999])
            ->orWhere('competition', 'like', 'Әлем чемпионаты%')
            ->delete();

        if ($removed > 0) {
            $this->line("Удалено матчей ЧМ из /games: {$removed}");
        }

        $synced = $service->syncFromApi();

        if ($synced) {
            $this->info('Данные обновлены из API-Football.');
        } else {
            $this->warn('API недоступен — используются данные жеребьёвки.');
            $service->syncIfNeeded();
        }

        return self::SUCCESS;
    }
}
