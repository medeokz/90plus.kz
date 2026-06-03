@php
    $scoreHome = $fixture['home_score'];
    $scoreAway = $fixture['away_score'];
    $hasScore = $scoreHome !== null && $scoreAway !== null && ($fixture['status'] ?? 'NS') !== 'NS';
    $scoreText = $hasScore ? ($scoreHome.' : '.$scoreAway) : '— : —';
    $time = \Illuminate\Support\Carbon::parse($fixture['kickoff_at'])->format('d.m, H:i');
    $isLive = in_array($fixture['status'] ?? 'NS', ['LIVE', '1H', '2H', 'HT', 'ET', 'BT', 'P'], true);
@endphp

<div class="wc-fixture {{ ($compact ?? false) ? 'wc-fixture--compact' : '' }}">
    <div class="wc-fixture-meta">
        <span>{{ $time }}</span>
        @if(!empty($fixture['round']))
            <span>{{ $fixture['round'] }}</span>
        @endif
    </div>
    <div class="wc-fixture-teams">
        <span class="wc-fixture-team">
            @if(!empty($fixture['home_team_flag']))
                <img src="{{ $fixture['home_team_flag'] }}" alt="" loading="lazy">
            @endif
            <span class="wc-fixture-team-name">{{ $fixture['home_team'] }}</span>
        </span>
        <strong class="wc-fixture-score {{ $isLive ? 'is-live' : '' }}">{{ $scoreText }}</strong>
        <span class="wc-fixture-team">
            @if(!empty($fixture['away_team_flag']))
                <img src="{{ $fixture['away_team_flag'] }}" alt="" loading="lazy">
            @endif
            <span class="wc-fixture-team-name">{{ $fixture['away_team'] }}</span>
        </span>
    </div>
    @if($isLive)
        <span class="wc-fixture-live">LIVE {{ $fixture['minute'] ?? '' }}'</span>
    @endif
</div>
