@extends('layouts.app')

@section('title', 'Матчтар — ' . config('app.name'))

@section('content')
<div class="fixture-page fixture-page--list">
    <div class="container container--narrow">
        <h1 class="fixture-list-heading">Матчтар</h1>
        <div class="fixture-list fixture-list--compact" id="fixture-list" data-feed-url="{{ route('fixtures.feed') }}">
            @include('fixtures.partials.list', ['fixtures' => $fixtures])
        </div>
    </div>
</div>
@endsection

@push('head')
    <link rel="stylesheet" href="{{ asset('css/fixture-page.css') }}">
@endpush

@push('scripts')
<script>
(function () {
    var list = document.getElementById('fixture-list');
    if (!list) return;

    var feedUrl = list.dataset.feedUrl;
    var intervalMs = 30000;
    var timer = null;

    function poll() {
        fetch(feedUrl, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            cache: 'no-store'
        })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (!data) return;

                if (data.html) {
                    list.innerHTML = data.html;
                }

                if (data.has_live) {
                    schedule(30000);
                } else {
                    schedule(60000);
                }
            })
            .catch(function () {
                schedule(60000);
            });
    }

    function schedule(ms) {
        intervalMs = ms;
        if (timer) clearInterval(timer);
        timer = setInterval(poll, intervalMs);
    }

    poll();
    schedule(intervalMs);

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            poll();
        }
    });
})();
</script>
@endpush
