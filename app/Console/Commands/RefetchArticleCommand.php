<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Services\ArticleContentParser;
use App\Services\TranslationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class RefetchArticleCommand extends Command
{
    protected $signature = 'articles:refetch {slug : Article slug} {--lang=en : Source language en|ru}';

    protected $description = 'Re-download article from source URL, strip ads, and retranslate';

    public function handle(ArticleContentParser $parser, TranslationService $translator): int
    {
        $article = Article::where('slug', $this->argument('slug'))->first();

        if (! $article) {
            $this->error('Article not found.');

            return self::FAILURE;
        }

        $this->info("Fetching: {$article->source_url}");

        $response = Http::timeout(45)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml',
                'Accept-Language' => 'en-US,en;q=0.9',
            ])
            ->get($article->source_url);

        if (! $response->successful()) {
            $this->error('Failed to download source page: '.$response->status());

            return self::FAILURE;
        }

        $parsed = $parser->parse($article->source_url, $response->body());

        if ($parsed['content'] === '') {
            $this->error('Could not extract article content.');

            return self::FAILURE;
        }

        $lang = $this->option('lang');

        $article->content_en = $parsed['content'];
        $article->summary_en = Str::limit(strip_tags($parsed['content']), 500);
        $article->content_kk = $translator->toKazakhFormatted($parsed['content'], $lang) ?: $parsed['content'];
        $article->summary_kk = $translator->toKazakh($article->summary_en, $lang) ?: $article->summary_en;

        if ($parsed['image']) {
            $article->image_url = $parsed['image'];
        }

        $article->save();

        $this->info('Done! Content re-parsed and translated ('.strlen($article->content_en).' chars).');

        return self::SUCCESS;
    }
}
