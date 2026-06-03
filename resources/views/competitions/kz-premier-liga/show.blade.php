@extends('layouts.app')

@section('title', $competition['name'] . ' ' . $overview['season_label'] . ' — ' . config('app.name'))
@section('meta_description', 'Қазақстан Премьер-лигасы: турнирлік кесте, жоспар, нәтижелер, стадиондар және командалар.')

@section('content')
<div class="wc-page">
    <div class="container">
        <div class="wc-hero">
            <div class="wc-hero-main">
                <p class="wc-breadcrumb">Жарыстар › {{ $competition['name'] }} › {{ $overview['season_label'] }}</p>
                <h1 class="wc-title">{{ $competition['name'] }}</h1>
                <p class="wc-subtitle">{{ $league['name'] ?? 'Қазақстан Премьер-лигасы' }} · {{ $overview['country'] }}</p>
            </div>
            <div class="wc-meta-card">
                @if($overview['start_date'] && $overview['end_date'])
                <div class="wc-meta-row">
                    <span>Кезең</span>
                    <strong>{{ \Illuminate\Support\Carbon::parse($overview['start_date'])->format('d.m.Y') }} — {{ \Illuminate\Support\Carbon::parse($overview['end_date'])->format('d.m.Y') }}</strong>
                </div>
                @endif
                <div class="wc-meta-row">
                    <span>Ойындар</span>
                    <strong>{{ $overview['played'] }} / {{ $overview['total_matches'] }}</strong>
                </div>
                <div class="wc-meta-row">
                    <span>Саты</span>
                    <strong>{{ $overview['stage'] }}</strong>
                </div>
            </div>
        </div>

        @include('competitions.kz-premier-liga.partials.tabs', ['tab' => $tab])

        @if($tab === 'tournament')
            @include('competitions.kz-premier-liga.partials.tournament')
        @elseif($tab === 'schedule')
            <div id="kpl-fixtures-feed" data-feed-url="{{ route('premier-liga.feed', ['tab' => 'schedule']) }}">
                @include('competitions.world-cup.partials.fixtures-list', [
                    'fixturesByDate' => $schedule,
                    'empty' => 'Алдағы ойындар жоқ.',
                ])
            </div>
        @elseif($tab === 'results')
            <div id="kpl-fixtures-feed" data-feed-url="{{ route('premier-liga.feed', ['tab' => 'results']) }}">
                @include('competitions.world-cup.partials.fixtures-list', [
                    'fixturesByDate' => $results,
                    'empty' => 'Аяқталған ойындар жоқ.',
                ])
            </div>
        @elseif($tab === 'stadiums')
            @include('competitions.kz-premier-liga.partials.stadiums')
        @else
            @include('competitions.kz-premier-liga.partials.teams')
        @endif
    </div>
</div>
@endsection

@push('head')
    <link rel="stylesheet" href="{{ asset('css/world-cup.css') }}">
@endpush

@if(in_array($tab, ['schedule', 'results'], true))
@push('scripts')
<script>
(function () {
    var root = document.getElementById('kpl-fixtures-feed');
    if (!root) return;

    var feedUrl = root.dataset.feedUrl;
    var intervalMs = 45000;

    function updateMeta(data) {
        var rows = document.querySelectorAll('.wc-meta-card .wc-meta-row strong');
        if (rows.length >= 2 && data.played !== undefined) {
            var total = @json($overview['total_matches']);
            rows[1].textContent = data.played + ' / ' + total;
        }
        if (rows.length >= 3 && data.stage) {
            rows[2].textContent = data.stage;
        }
    }

    function poll() {
        fetch(feedUrl, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            cache: 'no-store'
        })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (!data || !data.html) return;
                root.innerHTML = data.html;
                updateMeta(data);
            })
            .catch(function () {});
    }

    setInterval(poll, intervalMs);
})();
</script>
@endpush
@endif
