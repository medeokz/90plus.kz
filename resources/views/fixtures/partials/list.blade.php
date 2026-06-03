@forelse($fixtures as $fixture)
<a href="{{ route('fixtures.show', $fixture->external_id) }}" class="fixture-row" data-external-id="{{ $fixture->external_id }}">
    <div class="fixture-row-meta">
        <time class="fixture-kickoff">{{ $fixture->kickoff_at?->format('d.m, H:i') }}</time>
        <span class="fixture-competition" title="{{ $fixture->competition }}">{{ $fixture->competition }}</span>
    </div>
    <span class="fixture-home">{{ $fixture->home_team }}</span>
    <strong class="fixture-score">{{ $fixture->home_score ?? '–' }}:{{ $fixture->away_score ?? '–' }}</strong>
    <span class="fixture-away">{{ $fixture->away_team }}</span>
    <span class="fixture-list-status {{ $fixture->isLive() ? 'is-live' : '' }}">{{ $fixture->statusLabel() }}</span>
</a>
@empty
<p class="fixture-list-empty">Матчтар жоқ.</p>
@endforelse
