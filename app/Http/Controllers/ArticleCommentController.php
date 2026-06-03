<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreArticleCommentRequest;
use App\Models\Article;
use Illuminate\Http\RedirectResponse;

class ArticleCommentController extends Controller
{
    public function store(StoreArticleCommentRequest $request, string $slug): RedirectResponse
    {
        $article = Article::published()->where('slug', $slug)->firstOrFail();

        $article->comments()->create([
            'author_name' => trim($request->string('author_name')->toString()),
            'body' => trim($request->string('body')->toString()),
            'status' => 'approved',
            'ip_address' => $request->ip(),
        ]);

        return redirect()
            ->route('articles.show', $article->slug)
            ->withFragment('comments')
            ->with('comment_success', true);
    }
}
