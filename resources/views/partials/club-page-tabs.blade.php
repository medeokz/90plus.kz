<nav class="club-page__tabs" aria-label="Клуб бөлімдері">
    <a href="{{ route('clubs.show', ['slug' => $club->slug, 'tab' => 'info']) }}" class="{{ $tab === 'info' ? 'is-active' : '' }}">Барлық ақпарат</a>
    <a href="{{ route('clubs.show', ['slug' => $club->slug, 'tab' => 'squad']) }}" class="{{ $tab === 'squad' ? 'is-active' : '' }}">Құрам</a>
    <a href="{{ route('clubs.show', ['slug' => $club->slug, 'tab' => 'results']) }}" class="{{ $tab === 'results' ? 'is-active' : '' }}">Нәтижелер</a>
    <a href="{{ route('clubs.show', ['slug' => $club->slug, 'tab' => 'schedule']) }}" class="{{ $tab === 'schedule' ? 'is-active' : '' }}">Кесте</a>
</nav>
