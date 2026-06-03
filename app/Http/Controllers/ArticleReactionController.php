<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ArticleReactionController extends Controller
{
    private const ALLOWED_REACTIONS = ['like', 'dislike', 'funny', 'angry'];

    public function store(Request $request, string $slug): JsonResponse
    {
        $article = Article::published()->where('slug', $slug)->firstOrFail();

        $validated = $request->validate([
            'reaction' => ['required', 'string', 'in:'.implode(',', self::ALLOWED_REACTIONS)],
        ]);

        $sessionId = $request->session()->getId();

        $article->reactions()->updateOrCreate(
            ['session_id' => $sessionId],
            [
                'reaction' => $validated['reaction'],
                'ip_address' => $request->ip(),
            ]
        );

        return response()->json([
            'ok' => true,
            'selected' => $validated['reaction'],
            'counts' => $this->counts($article),
        ]);
    }

    /** @return array<string, int> */
    private function counts(Article $article): array
    {
        $raw = $article->reactions()
            ->selectRaw('reaction, COUNT(*) as total')
            ->groupBy('reaction')
            ->pluck('total', 'reaction');

        return [
            'like' => (int) ($raw['like'] ?? 0),
            'dislike' => (int) ($raw['dislike'] ?? 0),
            'funny' => (int) ($raw['funny'] ?? 0),
            'angry' => (int) ($raw['angry'] ?? 0),
        ];
    }
}

