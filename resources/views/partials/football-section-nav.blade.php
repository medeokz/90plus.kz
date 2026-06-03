<nav class="football-section-nav" aria-label="Футбол бөлімдері">

    <a href="{{ route('clubs.index') }}" class="{{ request()->routeIs('clubs.*', 'countries.*') ? 'is-active' : '' }}">Клубтар</a>

    <a href="{{ route('transfers.index') }}" class="{{ request()->routeIs('transfers.*') ? 'is-active' : '' }}">Трансферлер</a>

</nav>

