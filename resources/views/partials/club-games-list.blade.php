@if(!empty($games))
    <div class="club-games-list">
        @foreach($games as $game)
            @php
                $homeLogo = \App\Support\ClubLogoResolver::resolve(
                    $game['home_logo'] ?? null,
                    null,
                    $game['home_club_source_id'] ?? null,
                    $game['home_name'] ?? null
                );
                $awayLogo = \App\Support\ClubLogoResolver::resolve(
                    $game['away_logo'] ?? null,
                    null,
                    $game['away_club_source_id'] ?? null,
                    $game['away_name'] ?? null
                );
            @endphp
            <div class="game_block">
                <div class="game_block__meta">
                    <span class="game_block__date">{{ $game['date_text'] ?? '-' }}</span>
                    @if(!empty($game['competition']))
                        <span class="game_block__comp">
                            @if(!empty($game['competition_logo']))
                                <img src="{{ $game['competition_logo'] }}" alt="" loading="lazy" width="16" height="16">
                            @endif
                            {{ $game['competition'] }}
                        </span>
                    @endif
                </div>
                <div class="game_block__match">
                    <div class="game_block__team game_block__team--home">
                        @if($homeLogo)
                            <img src="{{ $homeLogo }}" alt="" loading="lazy" width="24" height="24" class="icon-club">
                        @endif
                        <span>{{ $game['home_name'] ?? '-' }}</span>
                    </div>
                    <div class="game_block__score">
                        @if(($game['home_score'] ?? '-') !== '-' && ($game['away_score'] ?? '-') !== '-')
                            {{ $game['home_score'] }}:{{ $game['away_score'] }}
                        @else
                            -:-
                        @endif
                    </div>
                    <div class="game_block__team game_block__team--away">
                        @if($awayLogo)
                            <img src="{{ $awayLogo }}" alt="" loading="lazy" width="24" height="24" class="icon-club">
                        @endif
                        <span>{{ $game['away_name'] ?? '-' }}</span>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@else
    <p class="club-empty">Деректер жоқ.</p>
@endif
