<section class="wc-section">
    <h2 class="wc-section-title">Турнирлік кесте</h2>
    <div class="league-table-wrap wc-third-place">
        <table class="league-table">
            <thead>
                <tr>
                    <th class="col-rank">#</th>
                    <th class="col-team">Команда</th>
                    <th class="col-stat">О</th>
                    <th class="col-stat">Же</th>
                    <th class="col-stat">Т</th>
                    <th class="col-stat">Жо</th>
                    <th class="col-stat">+/-</th>
                    <th class="col-stat col-points">Ұ</th>
                </tr>
            </thead>
            <tbody>
                @forelse($standings as $row)
                <tr class="{{ ($row['rank'] ?? 0) <= 3 ? 'row-top' : '' }}">
                    <td class="col-rank">{{ $row['rank'] }}</td>
                    <td class="col-team">
                        @if(!empty($row['logo']))
                            <img src="{{ $row['logo'] }}" alt="" class="team-logo" loading="lazy">
                        @endif
                        <span class="team-name">{{ $row['team'] }}</span>
                    </td>
                    <td class="col-stat">{{ $row['played'] }}</td>
                    <td class="col-stat">{{ $row['won'] }}</td>
                    <td class="col-stat">{{ $row['drawn'] }}</td>
                    <td class="col-stat">{{ $row['lost'] }}</td>
                    <td class="col-stat {{ ($row['gd'] ?? 0) > 0 ? 'gd-pos' : (($row['gd'] ?? 0) < 0 ? 'gd-neg' : '') }}">
                        {{ ($row['gd'] ?? 0) > 0 ? '+' : '' }}{{ $row['gd'] }}
                    </td>
                    <td class="col-stat col-points"><strong>{{ $row['points'] }}</strong></td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="wc-empty">Кесте жүктелуде…</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

@if(!empty($schedule_preview))
<section class="wc-section">
    <h2 class="wc-section-title">Алдағы ойындар</h2>
    <div class="wc-fixtures-list">
        @foreach($schedule_preview as $fixture)
            @include('competitions.world-cup.partials.fixture-row', ['fixture' => $fixture, 'compact' => true])
        @endforeach
    </div>
    <p class="kpl-more-link"><a href="{{ route('premier-liga.schedule') }}">Барлық жоспар →</a></p>
</section>
@endif

@if(!empty($results_preview))
<section class="wc-section">
    <h2 class="wc-section-title">Соңғы нәтижелер</h2>
    <div class="wc-fixtures-list">
        @foreach($results_preview as $fixture)
            @include('competitions.world-cup.partials.fixture-row', ['fixture' => $fixture, 'compact' => true])
        @endforeach
    </div>
    <p class="kpl-more-link"><a href="{{ route('premier-liga.results') }}">Барлық нәтижелер →</a></p>
</section>
@endif
