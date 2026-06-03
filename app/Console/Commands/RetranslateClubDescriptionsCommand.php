<?php

namespace App\Console\Commands;

use App\Models\Club;
use App\Services\Soccer365ClubService;
use Illuminate\Console\Command;

class RetranslateClubDescriptionsCommand extends Command
{
    protected $signature = 'clubs:translate-descriptions {--limit=0 : Max clubs to process}';

    protected $description = 'Translate existing club descriptions from Russian to Kazakh';

    public function handle(Soccer365ClubService $service): int
    {
        $limit = max(0, (int) $this->option('limit'));
        $query = Club::query()->whereNotNull('description')->orderBy('id');
        if ($limit > 0) {
            $query->limit($limit);
        }

        $updated = 0;
        $query->chunkById(20, function ($clubs) use ($service, &$updated) {
            foreach ($clubs as $club) {
                $translated = $service->translateClubDescription($club->description);
                if ($translated && $translated !== $club->description) {
                    $club->update(['description' => $translated]);
                    $updated++;
                }
            }
        });

        $this->info("Translated {$updated} club descriptions.");

        return self::SUCCESS;
    }
}
