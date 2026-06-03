@if(!empty($compactLiveMatches) || !empty($compactUpcomingMatches))
<section class="compact-matches">
    <div class="block_body_nopadding compact-matches__body">
            @if(!empty($compactLiveMatches))
            <div class="compact-matches__group">
                <h3 class="compact-matches__title">LIVE</h3>
                <div class="compact-matches__list">
                    @foreach($compactLiveMatches as $match)
                    <a href="{{ $match['url'] }}" class="compact-matches__item compact-matches__item--live">
                        <span class="compact-matches__status">{{ $match['status'] }}</span>
                        <span class="compact-matches__teams">{{ $match['home_team'] }} - {{ $match['away_team'] }}</span>
                        <span class="compact-matches__score">{{ $match['home_score'] ?? '-' }}:{{ $match['away_score'] ?? '-' }}</span>
                    </a>
                    @endforeach
                </div>
            </div>
            @endif

            @if(!empty($compactUpcomingMatches))
            <div class="compact-matches__group">
                <h3 class="compact-matches__title">Алда</h3>
                <div class="compact-matches__list">
                    @foreach($compactUpcomingMatches as $match)
                    <a href="{{ $match['url'] }}" class="compact-matches__item">
                        <span class="compact-matches__status">{{ $match['status'] }}</span>
                        <span class="compact-matches__teams">{{ $match['home_team'] }} - {{ $match['away_team'] }}</span>
                        <span class="compact-matches__league">{{ $match['competition'] }}</span>
                    </a>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
</section>
@endif

