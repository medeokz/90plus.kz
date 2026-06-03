@extends('layouts.app')

@section('title', 'Клубтар — ' . config('app.name'))

@section('content')
<section class="clubs-page">
    <div class="container">
        @include('partials.football-section-nav')

        <nav class="clubs-page__tabs" aria-label="Клубтар бөлімі">
            <a href="{{ route('clubs.index', ['tab' => 'countries']) }}" class="{{ $tab === 'countries' ? 'is-active' : '' }}">Елдер</a>
            <a href="{{ route('clubs.index', ['tab' => 'clubs']) }}" class="{{ $tab === 'clubs' ? 'is-active' : '' }}">Клубтар</a>
        </nav>

        @if($tab === 'countries')
            <h1>Елдер</h1>
            <div class="countries-grid">
                @forelse($countries as $country)
                    <a href="{{ route('countries.show', $country->slug) }}" class="country-card">
                        @if($country->flag_url)
                            <img src="{{ $country->flag_url }}" alt="{{ $country->name }}" loading="lazy" width="48" height="32" class="icon-flag">
                        @endif
                        <span class="country-card__name">{{ $country->name }}</span>
                        @if($country->clubs_count)
                            <span class="country-card__count">{{ $country->clubs_count }}</span>
                        @endif
                    </a>
                @empty
                    <p>Қазірше елдер жоқ. <code>php artisan countries:sync</code> командасын іске қосыңыз.</p>
                @endforelse
            </div>
            <div class="pagination-wrap">
                {{ $countries->links() }}
            </div>
        @else
            <h1>Клубтар</h1>
            <div class="clubs-grid">
                @forelse($clubs as $club)
                    @include('partials.club-card', ['club' => $club, 'showCountry' => true])
                @empty
                    <p>Қазірше клубтар жоқ.</p>
                @endforelse
            </div>
            <div class="pagination-wrap">
                {{ $clubs->links() }}
            </div>
        @endif
    </div>
</section>
@endsection
