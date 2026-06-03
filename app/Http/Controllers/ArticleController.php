<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Services\LeagueStandingsService;
use Illuminate\View\View;

class ArticleController extends Controller
{
    public function show(string $slug, LeagueStandingsService $standingsService): View
    {
        $article = Article::published()->where('slug', $slug)->firstOrFail();
        $moreNews = Article::published()
            ->where('id', '!=', $article->id)
            ->latest('published_at')
            ->take(6)
            ->get();

        $leagues = $standingsService->getAll();
        $comments = $article->approvedComments()->get();
        $rawReactionCounts = $article->reactions()
            ->selectRaw('reaction, COUNT(*) as total')
            ->groupBy('reaction')
            ->pluck('total', 'reaction');
        $reactionCounts = [
            'like' => (int) ($rawReactionCounts['like'] ?? 0),
            'dislike' => (int) ($rawReactionCounts['dislike'] ?? 0),
            'funny' => (int) ($rawReactionCounts['funny'] ?? 0),
            'angry' => (int) ($rawReactionCounts['angry'] ?? 0),
        ];
        $reactionSelected = $article->reactions()
            ->where('session_id', request()->session()->getId())
            ->value('reaction');

        return view('articles.show', compact(
            'article',
            'moreNews',
            'leagues',
            'comments',
            'reactionCounts',
            'reactionSelected'
        ));
    }
}
