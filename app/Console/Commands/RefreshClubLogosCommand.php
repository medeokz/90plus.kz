<?php

namespace App\Console\Commands;

use App\Services\Soccer365ClubService;
use Illuminate\Console\Command;

class RefreshClubLogosCommand extends Command
{
    protected $signature = 'clubs:refresh-logos {--batch=25 : Clubs per batch}';

    protected $description = 'Refresh club logos from soccer365.ru profile pages';

    public function handle(Soccer365ClubService $service): int
    {
        $batch = max(1, (int) $this->option('batch'));
        $updated = $service->refreshAllLogos($batch);
        $this->info("Updated {$updated} club logos.");

        return self::SUCCESS;
    }
}
