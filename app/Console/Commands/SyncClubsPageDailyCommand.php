<?php

namespace App\Console\Commands;

use App\Services\Soccer365ClubService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncClubsPageDailyCommand extends Command
{
    protected $signature = 'clubs:sync-daily
        {--pages=15 : Pages to scan on soccer365 for countries/clubs discovery}
        {--batch=15 : Clubs processed per batch during sync}';

    protected $description = 'Daily refresh of /clubs page (countries + clubs) from soccer365.ru';

    public function handle(Soccer365ClubService $clubService): int
    {
        $pages = max(1, (int) $this->option('pages'));
        $batch = max(1, (int) $this->option('batch'));

        $this->info('Step 1/3: syncing countries, flags and club links…');
        $this->call('countries:sync', [
            '--pages' => $pages,
            '--link-clubs' => true,
        ]);

        $this->info('Step 2/3: discovering clubs on soccer365.ru…');
        $discovered = $clubService->discoverAll($pages, 2);
        $clubService->backfillTransferClubIds();
        $this->info("Discovered club IDs: {$discovered}");

        $queued = DB::table('club_discovery')->count();
        if ($queued === 0) {
            $this->warn('No clubs in discovery queue.');

            return self::FAILURE;
        }

        $this->info("Step 3/3: syncing {$queued} clubs (batch {$batch})…");
        $result = $clubService->syncAll(0, 0, $batch, true);
        $this->info("Done. Processed: {$result['processed']}, clubs: {$result['clubs']}, squad rows: {$result['players']}");
        $this->info('Clubs in DB: '.DB::table('clubs')->count());

        return self::SUCCESS;
    }
}
