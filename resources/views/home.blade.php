@extends('layouts.app')

@section('title', config('app.name') . ' — Футбол жаңалықтары қазақ тілінде')

@section('content')

<div class="news-home">
    <div class="container">

        <div class="news-layout">

            <div class="news-main">

                @include('partials.slider-media', ['sliderItems' => $sliderItems])
                @include('partials.compact-matches', [
                    'compactLiveMatches' => $compactMatches['live'] ?? [],
                    'compactUpcomingMatches' => $compactMatches['upcoming'] ?? [],
                ])

                <div class="news-section-head" id="feed-head">
                    <h2 class="section-title">Жаңалықтар</h2>
                    <span class="news-count">{{ $articles->total() }} мақала</span>
                </div>

                <section class="news-feed" id="feed">
                    @if($articles->isEmpty())
                        <div class="empty-state">
                            <div class="empty-state-icon">📭</div>
                            <h3>Мақала табылмады</h3>
                            <p>Жаңа мақалалар алу үшін: <code>php artisan articles:fetch-hourly</code></p>
                        </div>
                    @else
                        @foreach($articles as $article)
                        <a href="{{ route('articles.show', $article->slug) }}" class="news-feed-item">
                            <div class="news-feed-thumb">
                                @if($article->image_url)
                                    <img src="{{ $article->image_display }}" alt="{{ $article->title_kk }}" loading="lazy">
                                @else
                                    <div class="news-thumb-placeholder">⚽</div>
                                @endif
                            </div>
                            <div class="news-feed-content">
                                <div class="news-feed-top">
                                    <time>{{ $article->published_at?->format('d.m.Y, H:i') }}</time>
                                </div>
                                <h3 class="news-feed-title">{{ $article->title_kk }}</h3>
                                <p class="news-feed-summary">{{ $article->summary_kk }}</p>
                            </div>
                        </a>
                        @endforeach

                        <div class="pagination-wrap">
                            {{ $articles->links() }}
                        </div>
                    @endif
                </section>
            </div>

            <aside class="news-sidebar">
                @include('partials.league-standings', ['leagues' => $leagues, 'compact' => true])
            </aside>

        </div>
    </div>
</div>
@endsection

@push('head')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
    <link rel="stylesheet" href="{{ asset('css/slider-media.css') }}">
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script src="{{ asset('js/slider-media.js') }}" defer></script>
@endpush
