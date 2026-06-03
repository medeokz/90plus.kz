@extends('layouts.app')

@section('title', $country->name . ' — ' . config('app.name'))

@section('content')
<section class="clubs-page">
    <div class="container">
        @include('partials.football-section-nav')

        <div class="country-page__head">
            @if($country->flag_url)
                <img src="{{ $country->flag_url }}" alt="{{ $country->name }}" loading="lazy" width="48" height="32" class="country-page__flag">
            @endif
            <div>
                <h1>{{ $country->name }}</h1>
                <p class="club-page__links">
                    <a href="{{ route('clubs.index', ['tab' => 'countries']) }}">← Елдер тізімі</a>
                    ·
                    <a href="{{ route('clubs.index', ['tab' => 'clubs']) }}">Барлық клубтар</a>
                </p>
            </div>
        </div>

        <div class="clubs-grid">
            @forelse($clubs as $club)
                @include('partials.club-card', ['club' => $club])
            @empty
                <p>Бұл елде клубтар табылмады.</p>
            @endforelse
        </div>

        <div class="pagination-wrap">
            {{ $clubs->links() }}
        </div>
    </div>
</section>
@endsection
