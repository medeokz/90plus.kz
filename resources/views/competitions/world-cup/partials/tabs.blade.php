<nav class="wc-tabs" aria-label="Турнир бөлімдері">
    <a href="{{ route('world-cup.tournament') }}" class="wc-tab {{ $tab === 'tournament' ? 'active' : '' }}">Турнир</a>
    <a href="{{ route('world-cup.schedule') }}" class="wc-tab {{ $tab === 'schedule' ? 'active' : '' }}">Жоспар</a>
    <a href="{{ route('world-cup.results') }}" class="wc-tab {{ $tab === 'results' ? 'active' : '' }}">Нәтижелер</a>
</nav>
