@extends('layouts.app')

@section('title', 'Трансферлер — ' . config('app.name'))

@section('content')
@php
    $positionMap = [
        'вратарь' => 'Қақпашы',
        'защитник' => 'Қорғаушы',
        'полузащитник' => 'Жартылай қорғаушы',
        'нападающий' => 'Шабуылшы',
    ];
@endphp
<section class="transfers-page">
    <div class="container">
        @include('partials.football-section-nav')

        <div class="transfers-page__head">
            <h1>Трансферлер</h1>
            @if($clubFilter)
                <p class="transfers-page__filter">
                    <span class="transfers-cell-with-icon">
                        @if($clubFilter->logo_url)
                            <img src="{{ $clubFilter->logo_url }}" alt="" loading="lazy" width="20" height="20">
                        @endif
                        Клуб: <a href="{{ route('clubs.show', $clubFilter->slug) }}">{{ $clubFilter->name }}</a>
                    </span>
                    · <a href="{{ route('transfers.index') }}">Барлық трансферлер</a>
                </p>
            @endif
        </div>

        <div class="transfers-table-wrap">
            <table class="transfers-table">
                <thead>
                    <tr>
                        <th>Ойыншы</th>
                        <th>Қайдан</th>
                        <th>Қайда</th>
                        <th>Күні</th>
                        <th>Трансфер құны</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($transfers as $transfer)
                    <tr>
                        <td>
                            <div class="transfers-player">
                                <div class="transfers-cell-with-icon">
                                    @if($transfer->player_icon)
                                        <img src="{{ $transfer->player_icon }}" alt="" loading="lazy" width="22" height="22">
                                    @endif
                                    <span>{{ $transfer->player_name }}</span>
                                </div>
                                @if($transfer->position)
                                    @php
                                        $positionKey = mb_strtolower(trim($transfer->position), 'UTF-8');
                                        $positionLabel = $positionMap[$positionKey] ?? $transfer->position;
                                    @endphp
                                    <small>{{ $positionLabel }}</small>
                                @endif
                            </div>
                        </td>
                        <td>
                            <div class="transfers-cell-with-icon">
                                @if($transfer->fromClub?->logo_url)
                                    <img src="{{ $transfer->fromClub->logo_url }}" alt="" loading="lazy" width="18" height="18">
                                @elseif($transfer->from_club_icon)
                                    <img src="{{ $transfer->from_club_icon }}" alt="" loading="lazy" width="18" height="18">
                                @endif
                                @if($transfer->fromClub)
                                    <a href="{{ route('clubs.show', $transfer->fromClub->slug) }}">{{ $transfer->from_club }}</a>
                                @else
                                    <span>{{ $transfer->from_club ?: '-' }}</span>
                                @endif
                            </div>
                        </td>
                        <td>
                            <div class="transfers-cell-with-icon">
                                @if($transfer->toClub?->logo_url)
                                    <img src="{{ $transfer->toClub->logo_url }}" alt="" loading="lazy" width="18" height="18">
                                @elseif($transfer->to_club_icon)
                                    <img src="{{ $transfer->to_club_icon }}" alt="" loading="lazy" width="18" height="18">
                                @endif
                                @if($transfer->toClub)
                                    <a href="{{ route('clubs.show', $transfer->toClub->slug) }}">{{ $transfer->to_club }}</a>
                                @else
                                    <span>{{ $transfer->to_club ?: '-' }}</span>
                                @endif
                            </div>
                        </td>
                        <td>{{ $transfer->date_text ?: ($transfer->transfer_date?->format('d.m.Y') ?? '-') }}</td>
                        <td>{{ str_ireplace(['Бесплатно', 'Аренда'], ['Тегін', 'Жалдау'], $transfer->fee ?: '-') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">Қазірше трансфер деректері жоқ.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination-wrap">
            {{ $transfers->links() }}
        </div>
    </div>
</section>
@endsection
