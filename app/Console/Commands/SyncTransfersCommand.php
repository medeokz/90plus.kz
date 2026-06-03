<?php

namespace App\Console\Commands;

use App\Services\Soccer365TransfersService;
use Illuminate\Console\Command;

class SyncTransfersCommand extends Command
{
    protected $signature = 'transfers:sync
        {--url=https://soccer365.ru/transfers/ : Source URL}
        {--season= : Season label, e.g. "Лето 2026"}';

    protected $description = 'Parse and sync transfers from soccer365.ru/transfers';

    public function handle(Soccer365TransfersService $service): int
    {
        $url = (string) $this->option('url');
        $season = $this->option('season') ? (string) $this->option('season') : null;

        $this->info('Fetching transfers...');
        $count = $service->sync($url, $season);

        $this->info("Done. Synced {$count} transfers.");

        return self::SUCCESS;
    }
}

