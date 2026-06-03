@extends('layouts.app')

@section('title', $competition['name'] . ' — ' . config('app.name'))
@section('meta_description', '2026 FIFA Әлем чемпионаты: топтық кесте, сетка, кесте және нәтижелер.')

@section('content')
<div class="wc-page">
    <div class="container">
        <div class="wc-hero">
            <div class="wc-hero-main">
                <p class="wc-breadcrumb">Соревнования › {{ $competition['name'] }}</p>
                <h1 class="wc-title">{{ $competition['name'] }}</h1>
                <p class="wc-subtitle">FIFA World Cup · USA · Canada · Mexico</p>
            </div>
            <div class="wc-meta-card">
                <div class="wc-meta-row">
                    <span>Кезең</span>
                    <strong>{{ \Illuminate\Support\Carbon::parse($overview['start_date'])->format('d.m.Y') }} — {{ \Illuminate\Support\Carbon::parse($overview['end_date'])->format('d.m.Y') }}</strong>
                </div>
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

        @include('competitions.world-cup.partials.tabs', ['tab' => $tab])

        @if($tab === 'tournament')
            @include('competitions.world-cup.partials.tournament')
        @elseif($tab === 'schedule')
            @include('competitions.world-cup.partials.fixtures-list', [
                'fixturesByDate' => $schedule,
                'empty' => 'Алдағы ойындар жоқ.',
            ])
        @else
            @include('competitions.world-cup.partials.fixtures-list', [
                'fixturesByDate' => $results,
                'empty' => 'Аяқталған ойындар жоқ.',
            ])
        @endif
    </div>
</div>
@endsection

@push('head')
    <link rel="stylesheet" href="{{ asset('css/world-cup.css') }}">
@endpush
