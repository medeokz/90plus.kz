<?php

namespace App\Console\Commands;

use App\Services\Soccer365ClubService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncClubsCommand extends Command
{
    protected $signature = 'clubs:sync
        {--discover : Collect club IDs from soccer365 (no sync unless --all)}
        {--all : Sync all discovered clubs (skip re-discovery with --no-discover)}
        {--no-discover : Skip discovery step when used with --all}
        {--limit=60 : Clubs to sync per run (use 0 with --all for no limit)}
        {--offset=0 : Skip first N discovered clubs}
        {--batch=60 : Batch size per run}
        {--pages=15 : Pagination depth for discovery}
        {--url= : Parse one club URL only}';

    protected $description = 'Discover and sync soccer365 clubs and squad players';

    public function handle(Soccer365ClubService $service): int
    {
        $singleUrl = $this->option('url');
        if ($singleUrl) {
            $res = $service->syncClub((string) $singleUrl);
            $this->info('Club parsed: '.($res['club'] ? 'yes' : 'no').', players: '.$res['players']);

            return self::SUCCESS;
        }

        if ($this->option('discover') || ($this->option('all') && ! $this->option('no-discover'))) {
            $pages = max(1, (int) $this->option('pages'));
            $count = $service->discoverAll($pages, $this->option('all') ? 2 : 1);
            $service->backfillTransferClubIds();
            $this->info("Discovered club IDs: {$count} (queued in club_discovery)");
        }

        if ($this->option('discover') && ! $this->option('all')) {
            $this->info('Discovery only. Run: php artisan clubs:sync --all');

            return self::SUCCESS;
        }

        $limit = $this->option('all') ? 0 : max(1, (int) $this->option('limit'));
        $offset = max(0, (int) $this->option('offset'));
        $batch = max(1, (int) $this->option('batch'));

        $total = DB::table('club_discovery')->count();
        if ($total === 0) {
            $this->warn('No discovered clubs. Run: php artisan clubs:sync --discover');

            return self::FAILURE;
        }

        $res = $service->syncAll($limit, $offset, $batch, true);
        $this->info("Done. Queued: {$res['urls']}, processed: {$res['processed']}, clubs: {$res['clubs']}, players rows: {$res['players']}");
        $this->info('Clubs in DB: '.DB::table('clubs')->count());

        return self::SUCCESS;
    }
}
