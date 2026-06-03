@extends('layouts.app')

@section('title', $club->name . ' — ' . config('app.name'))

@section('content')
<section class="club-page">
    <div class="container">
        @include('partials.football-section-nav')

        <div class="profile_head">
            <div class="profile_foto">
                @if($club->logo_url)
                    <img src="{{ $club->logo_url }}" alt="{{ $club->name }}" loading="lazy" width="150" height="150">
                @endif
            </div>
            <div class="profile_info">
                <h1 class="profile_info_title">{{ $club->name }}</h1>
                @if($club->name_en)
                    <div class="profile_en_title">{{ $club->name_en }}</div>
                @endif
                @if($club->description)
                    <div class="profile_wiki">{{ $club->description }}</div>
                @endif
                @if(!empty($profile))
                    <table class="profile_params">
                        <tbody>
                            @if(!empty($profile['full_name']))
                                <tr><td class="params_key">Толық атауы</td><td>{{ $profile['full_name'] }}</td></tr>
                            @endif
                            @if(!empty($profile['coach_name']))
                                <tr>
                                    <td class="params_key">Бас бапкер</td>
                                    <td>
                                        <div class="transfers-cell-with-icon">
                                            @if(!empty($profile['coach_photo_url']))
                                                <img src="{{ $profile['coach_photo_url'] }}" alt="" loading="lazy" width="24" height="24" class="profile-coach-photo">
                                            @endif
                                            <span>{{ $profile['coach_name'] }}</span>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                            @if(!empty($profile['stadium_name']) || !empty($profile['stadium_city']))
                                <tr>
                                    <td class="params_key">Стадион</td>
                                    <td>
                                        @if($club->countryRecord?->flag_url)
                                            <div class="transfers-cell-with-icon">
                                                <img src="{{ $club->countryRecord->flag_url }}" alt="" loading="lazy" width="18" height="12" class="icon-flag">
                                                <span>
                                                    {{ $profile['stadium_name'] ?? '' }}
                                                    @if(!empty($profile['stadium_city']))
                                                        <span class="min_gray">{{ $profile['stadium_city'] }}</span>
                                                    @endif
                                                </span>
                                            </div>
                                        @else
                                            {{ $profile['stadium_name'] ?? '' }}
                                            @if(!empty($profile['stadium_city']))
                                                <span class="min_gray">{{ $profile['stadium_city'] }}</span>
                                            @endif
                                        @endif
                                    </td>
                                </tr>
                            @endif
                            @if(!empty($profile['founded']))
                                <tr><td class="params_key">Негізделген жылы</td><td>{{ $profile['founded'] }}</td></tr>
                            @endif
                            @if(!empty($profile['uefa_rank']))
                                <tr><td class="params_key">УЕФА рейтингі</td><td>{{ $profile['uefa_rank'] }}</td></tr>
                            @endif
                            @if(!empty($profile['competitions']))
                                <tr>
                                    <td class="params_key">Жарыстар</td>
                                    <td class="params_comp">
                                        @foreach($profile['competitions'] as $competition)
                                            <span class="transfers-cell-with-icon club-competition-tag">
                                                @if(!empty($competition['logo_url']))
                                                    <img src="{{ $competition['logo_url'] }}" alt="" loading="lazy" width="16" height="16">
                                                @endif
                                                <span>{{ $competition['name'] }}</span>
                                            </span>
                                        @endforeach
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                @endif
                <p class="club-page__links">
                    <a href="{{ route('clubs.index', ['tab' => 'clubs']) }}">← Клубтар тізімі</a>
                    @if($club->countryRecord)
                        · <a href="{{ route('countries.show', $club->countryRecord->slug) }}">{{ $club->countryRecord->name }}</a>
                    @endif
                    · <a href="{{ route('transfers.index', ['club' => $club->slug]) }}">Трансферлер</a>
                </p>
            </div>
        </div>

        @include('partials.club-page-tabs')

        @if($tab === 'info')
            @if(!empty($profile['standings']))
                @foreach($profile['standings'] as $standing)
                    <div class="club-section">
                        <h2 class="club-section__title">{{ $standing['competition'] ?: 'Турнир кестесі' }}</h2>
                        <div class="transfers-table-wrap">
                            <table class="transfers-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Клуб</th>
                                        <th>О</th>
                                        <th>+/-</th>
                                        <th>Ұ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($standing['rows'] as $row)
                                        @php
                                            $rowLogo = \App\Support\ClubLogoResolver::resolve(
                                                $row['club_logo'] ?? null,
                                                $row['club_url'] ?? null,
                                                null,
                                                $row['club_name'] ?? null
                                            );
                                        @endphp
                                        <tr>
                                            <td>{{ $row['position'] ?? '-' }}</td>
                                            <td>
                                                <div class="transfers-cell-with-icon">
                                                    @if($rowLogo)
                                                        <img src="{{ $rowLogo }}" alt="" loading="lazy" width="20" height="20" class="icon-club">
                                                    @endif
                                                    <span>{{ $row['club_name'] ?? '-' }}</span>
                                                </div>
                                            </td>
                                            <td>{{ $row['played'] ?? '-' }}</td>
                                            <td>{{ $row['goal_diff'] ?? '-' }}</td>
                                            <td><strong>{{ $row['points'] ?? '-' }}</strong></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endforeach
            @endif

            @if($recentTransfers->isNotEmpty())
                <div class="club-section">
                    <h2 class="club-section__title">Соңғы трансферлер</h2>
                    <div class="transfers-table-wrap">
                        <table class="transfers-table transfers-table--compact">
                            <thead>
                                <tr>
                                    <th>Ойыншы</th>
                                    <th>Қайдан</th>
                                    <th>Қайда</th>
                                    <th>Күні</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($recentTransfers as $transfer)
                                <tr>
                                    <td>{{ $transfer->player_name }}</td>
                                    <td>
                                        <div class="transfers-cell-with-icon">
                                            @if($transfer->fromClub?->logo_url)
                                                <img src="{{ $transfer->fromClub->logo_url }}" alt="" loading="lazy" width="24" height="24" class="icon-club">
                                            @endif
                                            <span>{{ $transfer->from_club ?: '-' }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="transfers-cell-with-icon">
                                            @if($transfer->toClub?->logo_url)
                                                <img src="{{ $transfer->toClub->logo_url }}" alt="" loading="lazy" width="24" height="24" class="icon-club">
                                            @endif
                                            <span>{{ $transfer->to_club ?: '-' }}</span>
                                        </div>
                                    </td>
                                    <td>{{ $transfer->date_text ?: ($transfer->transfer_date?->format('d.m.Y') ?? '-') }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <div class="club-section">
                <h2 class="club-section__title">Жақындағы ойындар</h2>
                @include('partials.club-games-list', ['games' => array_slice($profile['schedule'] ?? [], 0, 10)])
            </div>
        @endif

        @if($tab === 'squad')
            <div class="club-section">
                <h2 class="club-section__title">Құрам</h2>
                <div class="transfers-table-wrap">
                    <table class="transfers-table squad-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Ойыншы</th>
                                <th>Позиция</th>
                                <th>Жасы</th>
                                <th>Елі</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($players as $player)
                            <tr>
                                <td>{{ $player->pivot->number ?: '-' }}</td>
                                <td>
                                    <div class="transfers-cell-with-icon">
                                        @if($player->photo_url)
                                            <img src="{{ $player->photo_url }}" alt="" loading="lazy" width="40" height="40" class="icon-player">
                                        @endif
                                        <span>{{ $player->name }}</span>
                                    </div>
                                </td>
                                <td>{{ $player->pivot->position ?: '-' }}</td>
                                <td>{{ $player->pivot->age ?: $player->age ?: '-' }}</td>
                                <td>
                                    <div class="transfers-cell-with-icon">
                                        @if($player->nationality_flag_url)
                                            <img src="{{ $player->nationality_flag_url }}" alt="" loading="lazy" width="20" height="14" class="icon-flag">
                                        @endif
                                        <span>{{ $player->pivot->nationality ?: $player->nationality ?: '-' }}</span>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5">Бұл клуб үшін ойыншылар табылмады.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if($tab === 'results')
            <div class="club-section">
                <h2 class="club-section__title">Нәтижелер</h2>
                @include('partials.club-games-list', ['games' => $profile['results'] ?? []])
            </div>
        @endif

        @if($tab === 'schedule')
            <div class="club-section">
                <h2 class="club-section__title">Кесте</h2>
                @include('partials.club-games-list', ['games' => $profile['schedule'] ?? []])
            </div>
        @endif
    </div>
</section>
@endsection
