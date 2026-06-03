<?php

namespace App\Console\Commands;

use App\Services\Soccer365ClubService;
use Illuminate\Console\Command;

class BackfillClubSquadCommand extends Command
{
    protected $signature = 'clubs:backfill-squad
        {--all : Backfill every club, not only rows with missing position/age}
        {--limit=0 : Max clubs to process (0 = no limit)}
        {--batch=30 : Chunk size}';

    protected $description = 'Re-parse squad position and age for clubs from soccer365';

    public function handle(Soccer365ClubService $service): int
    {
        $limit = max(0, (int) $this->option('limit'));
        $batch = max(1, (int) $this->option('batch'));
        $emptyOnly = ! $this->option('all');

        $this->info($emptyOnly
            ? 'Backfilling squads for clubs with missing position or age…'
            : 'Backfilling squads for all clubs…');

        $result = $service->backfillSquads($emptyOnly, $limit, $batch);

        $this->info("Done. Processed: {$result['processed']}, clubs: {$result['clubs']}, player rows: {$result['players']}");

        return self::SUCCESS;
    }
}
