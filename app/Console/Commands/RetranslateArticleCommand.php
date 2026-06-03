<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Services\TranslationService;
use Illuminate\Console\Command;

class RetranslateArticleCommand extends Command
{
    protected $signature = 'articles:retranslate {slug : Article slug} {--lang=en : Source language en|ru}';

    protected $description = 'Retranslate article title, summary and content to Kazakh';

    public function handle(TranslationService $translator): int
    {
        $article = Article::where('slug', $this->argument('slug'))->first();

        if (! $article) {
            $this->error('Article not found.');

            return self::FAILURE;
        }

        $lang = $this->option('lang');

        $this->info("Translating: {$article->title_en}");

        $article->title_kk = $translator->toKazakh($article->title_en, $lang) ?: $article->title_en;
        $article->summary_kk = $translator->toKazakh($article->summary_en, $lang) ?: $article->summary_en;
        $article->content_kk = $translator->toKazakhFormatted($article->content_en, $lang) ?: $article->content_en;
        $article->save();

        $same = $article->content_kk === $article->content_en;
        $this->info($same ? 'Warning: content still matches English (check logs).' : 'Done! Content translated to Kazakh.');

        return self::SUCCESS;
    }
}
