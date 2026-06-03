<nav class="wc-tabs" aria-label="Премьер-лига бөлімдері">
    <a href="{{ route('premier-liga.tournament') }}" class="wc-tab {{ $tab === 'tournament' ? 'active' : '' }}">Турнир</a>
    <a href="{{ route('premier-liga.schedule') }}" class="wc-tab {{ $tab === 'schedule' ? 'active' : '' }}">Жоспар</a>
    <a href="{{ route('premier-liga.results') }}" class="wc-tab {{ $tab === 'results' ? 'active' : '' }}">Нәтижелер</a>
    <a href="{{ route('premier-liga.stadiums') }}" class="wc-tab {{ $tab === 'stadiums' ? 'active' : '' }}">Стадиондар</a>
    <a href="{{ route('premier-liga.teams') }}" class="wc-tab {{ $tab === 'teams' ? 'active' : '' }}">Командалар</a>
</nav>
