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
        $sources = config('football.sources', []);
        $count = count($sources);

        $this->info("Hourly fetch: 1 article from each of {$count} sources (full text + Kazakh translation)...");

        $imported = $fetchService->fetchHourlyFromAllSources();

        if ($imported > 0) {
            $this->info("Done! Imported {$imported} new articles from {$count} sources.");
        } else {
            $this->warn('No new articles found from any source.');
        }

        return self::SUCCESS;
    }
}
