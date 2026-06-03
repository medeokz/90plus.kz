<?php

namespace App\Console\Commands;

use App\Services\ArticleFetchService;
use Illuminate\Console\Command;

class FetchArticlesCommand extends Command
{
    protected $signature = 'articles:fetch {--limit=5 : Articles per source} {--full : Fetch full article text from source page}';

    protected $description = 'Fetch football articles from English RSS feeds and translate to Kazakh';

    public function handle(ArticleFetchService $fetchService): int
    {
        $limit = (int) $this->option('limit');
        $full = (bool) $this->option('full');

        $this->info("Fetching up to {$limit} articles per source".($full ? ' (full text)' : '').'...');

        $count = $fetchService->fetchAll($limit, $full);

        $this->info("Done! Imported {$count} new articles.");

        return self::SUCCESS;
    }
}
