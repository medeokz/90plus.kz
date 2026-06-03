@extends('layouts.app')

@section('title', $article->title_kk . ' — ' . config('app.name'))
@section('meta_description', \Illuminate\Support\Str::limit($article->summary_kk, 160))

@section('content')
<section class="article-page">
    <div class="container">
        <nav class="breadcrumb">
            <a href="{{ route('home') }}">Басты бет</a>
            <span>›</span>
            <span>{{ \Illuminate\Support\Str::limit($article->title_kk, 50) }}</span>
        </nav>

        <div class="article-page-inner">
            <div class="article-page-main">
                <article class="article-content-card">
                    @if($article->image_url)
                        <img class="article-hero-image" src="{{ $article->image_display }}" alt="{{ $article->title_kk }}">
                    @else
                        <div class="article-hero-image placeholder">⚽</div>
                    @endif

                    <div class="article-body">
                        <div class="article-body-meta">
                            <span>📅 {{ $article->published_at?->translatedFormat('d F Y, H:i') }}</span>
                            <span>🕐 {{ $article->published_at?->diffForHumans() }}</span>
                        </div>

                        <h1>{{ $article->title_kk }}</h1>

                        <div class="article-text">
                            {!! app(\App\Services\ArticleContentFormatter::class)->toHtml($article->content_kk) !!}
                        </div>

                        @include('partials.article-reactions')

                        @include('partials.article-comments', ['article' => $article, 'comments' => $comments])

                        @include('partials.more-news', ['articles' => $moreNews])
                    </div>
                </article>
            </div>

            <aside class="article-page-sidebar">
                @include('partials.league-standings', ['leagues' => $leagues, 'compact' => true])
            </aside>
        </div>
    </div>
</section>
@endsection
