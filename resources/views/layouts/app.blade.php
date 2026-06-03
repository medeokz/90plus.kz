<!DOCTYPE html>
<html lang="kk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="@yield('meta_description', 'Футбол жаңалықтары қазақ тілінде')">
    <title>@yield('title', config('app.name'))</title>
    <link rel="icon" href="{{ asset('images/logo.png') }}" type="image/png">
    <link rel="stylesheet" href="{{ asset('css/fonts-livesport.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @stack('head')
</head>
<body>
    <header class="site-header">
        <div class="container header-inner">
            @include('partials.logo')

            <button type="button" class="nav-toggle" aria-expanded="false" aria-controls="site-nav" aria-label="Мәзірді ашу">
                <span class="nav-toggle__bar" aria-hidden="true"></span>
                <span class="nav-toggle__bar" aria-hidden="true"></span>
                <span class="nav-toggle__bar" aria-hidden="true"></span>
            </button>

            <nav id="site-nav" class="site-nav" aria-label="Негізгі мәзір">
                <ul class="main-nav">
                    <li><a href="{{ route('home') }}" class="{{ request()->routeIs('home') ? 'active' : '' }}">Басты бет</a></li>
                    <li><a href="{{ route('fixtures.index') }}" class="{{ request()->routeIs('fixtures.*') ? 'active' : '' }}">Матчтар</a></li>
                    <li><a href="{{ route('clubs.index') }}" class="{{ request()->routeIs('clubs.*', 'countries.*') ? 'active' : '' }}">Клубтар</a></li>
                    <li><a href="{{ route('transfers.index') }}" class="{{ request()->routeIs('transfers.*') ? 'active' : '' }}">Трансферлер</a></li>
                    <li><a href="{{ route('premier-liga.tournament') }}" class="{{ request()->routeIs('premier-liga.*') ? 'active' : '' }}">Премьер-лига</a></li>
                    <li><a href="{{ route('world-cup.tournament') }}" class="{{ request()->routeIs('world-cup.*') ? 'active' : '' }}">ӘЧ 2026</a></li>
                    <li><a href="{{ route('home') }}#feed">Жаңалықтар</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main>
        @yield('content')
    </main>

    <footer class="site-footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-brand">
                    @include('partials.logo', ['class' => 'logo--footer'])
                    <p>Футбол жаңалықтарын қазақ тілінде оқуға арналған заманауи портал.</p>
                </div>
                <div class="footer-links">
                    <h4>Навигация</h4>
                    <ul>
                        <li><a href="{{ route('home') }}">Басты бет</a></li>
                        <li><a href="{{ route('home') }}#feed">Жаңалықтар</a></li>
                        <li><a href="{{ route('fixtures.index') }}">Матчтар</a></li>
                        <li><a href="{{ route('clubs.index') }}">Клубтар</a></li>
                        <li><a href="{{ route('transfers.index') }}">Трансферлер</a></li>
                        <li><a href="{{ route('premier-liga.tournament') }}">Премьер-лига</a></li>
                        <li><a href="{{ route('world-cup.tournament') }}">ӘЧ 2026</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <span>© {{ date('Y') }} 90plus.kz — Барлық құқықтар қорғалған</span>
            </div>
        </div>
    </footer>

    <script src="{{ asset('js/site.js') }}" defer></script>
    @stack('scripts')
</body>
</html>
