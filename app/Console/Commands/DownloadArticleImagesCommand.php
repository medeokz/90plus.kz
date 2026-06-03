<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Services\ImageDownloadService;
use Illuminate\Console\Command;

class DownloadArticleImagesCommand extends Command
{
    protected $signature = 'articles:download-images';

    protected $description = 'Download original images locally for articles with external image URLs';

    public function handle(ImageDownloadService $downloader): int
    {
        $articles = Article::where('image_url', 'like', 'http%')->get();
        $this->info("Found {$articles->count()} articles with external images...");

        $downloaded = 0;
        foreach ($articles as $article) {
            $local = $downloader->download($article->image_url, $article->source_url);
            if ($local !== null) {
                $article->update(['image_url' => $local]);
                $downloaded++;
                $this->line("  ✓ {$article->title_kk}");
            }
        }

        $this->info("Downloaded {$downloaded} images to public/images/articles/");

        return self::SUCCESS;
    }
}
