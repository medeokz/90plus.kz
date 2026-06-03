<section class="wc-section">
    @forelse($fixturesByDate as $date => $fixtures)
        <div class="wc-date-block">
            <h2 class="wc-date-title">{{ $date }}</h2>
            <div class="wc-fixtures-list">
                @foreach($fixtures as $fixture)
                    @include('competitions.world-cup.partials.fixture-row', ['fixture' => $fixture])
                @endforeach
            </div>
        </div>
    @empty
        <p class="wc-empty">{{ $empty ?? 'Матчтар жоқ.' }}</p>
    @endforelse
</section>
