<?php

namespace App\Console\Commands;

use App\Services\ArticleFetchService;
use Illuminate\Console\Command;

class FetchHourlyArticleCommand extends Command
{
    protected $signature = 'articles:fetch-hourly';

    protected $description = 'Fetch 1 full article from each source and translate to Kazakh';

    public function handle(ArticleFetchService $fetchService): int
    {
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);

        $batch = (int) config('football.article_fetch_batch_size', 6);

        $this->info("Article fetch: batch of {$batch} sources (rotation, full text + Kazakh)...");

        $imported = $fetchService->fetchHourlyFromAllSources();

        if ($imported > 0) {
            $this->info("Done! Imported {$imported} new articles in this batch.");
        } else {
            $this->warn('No new articles in this batch (sources may be skipped or already imported).');
        }

        return self::SUCCESS;
    }
}
