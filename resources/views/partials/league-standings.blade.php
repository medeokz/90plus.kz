{{-- League standings tables --}}
<section class="league-standings {{ ($compact ?? false) ? 'league-standings--compact' : '' }}" @if(empty($compact)) id="standings" @endif>
    <div class="league-standings-head">
        <h2 class="section-title">{{ ($compact ?? false) ? '🏆 Кестелер' : '🏆 Чемпионаттар кестесі' }}</h2>
    </div>

    @include('partials.league-tabs', ['leagues' => $leagues, 'compact' => $compact ?? false])

    @foreach($leagues as $index => $league)
        @php
            $total = count($league['standings']);
            $relegationFrom = max(1, $total - 2);
        @endphp
        <div class="league-panel {{ $index === 0 ? 'active' : '' }}"
             data-league-panel="{{ $league['key'] }}"
             role="tabpanel">
            <h3 class="league-panel-title">
                {{ $league['name'] }}
                <span class="league-team-count">{{ $total }} клуб</span>
            </h3>

            @if(empty($league['standings']))
                <p class="league-empty">Кесте деректері жоқ</p>
            @else
                <div class="league-table-wrap">
                    <table class="league-table">
                        <thead>
                            <tr>
                                <th class="col-rank">#</th>
                                <th class="col-team">Команда</th>
                                <th class="col-stat">О</th>
                                <th class="col-stat">Же</th>
                                <th class="col-stat">Т</th>
                                <th class="col-stat">Жо</th>
                                <th class="col-stat">±</th>
                                <th class="col-stat col-points">Ұ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($league['standings'] as $row)
                            <tr class="{{ $row['rank'] <= 4 ? 'row-top' : ($row['rank'] >= $relegationFrom ? 'row-bottom' : '') }}">
                                <td class="col-rank">{{ $row['rank'] }}</td>
                                <td class="col-team">
                                    @if(!empty($row['logo']))
                                        <img src="{{ $row['logo'] }}" alt="" class="team-logo" loading="lazy">
                                    @endif
                                    <span class="team-name" title="{{ $row['team'] }}">{{ $row['team'] }}</span>
                                </td>
                                <td class="col-stat">{{ $row['played'] }}</td>
                                <td class="col-stat">{{ $row['won'] }}</td>
                                <td class="col-stat">{{ $row['drawn'] }}</td>
                                <td class="col-stat">{{ $row['lost'] }}</td>
                                <td class="col-stat {{ $row['gd'] > 0 ? 'gd-pos' : ($row['gd'] < 0 ? 'gd-neg' : '') }}">
                                    {{ $row['gd'] > 0 ? '+' : '' }}{{ $row['gd'] }}
                                </td>
                                <td class="col-stat col-points"><strong>{{ $row['points'] }}</strong></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if(empty($compact))
                <p class="league-legend">
                    <span class="legend-top">● Топ-4</span>
                    <span class="legend-bottom">● Түсу аймағы</span>
                </p>
                @endif
            @endif
        </div>
    @endforeach
</section>

@once
    @push('scripts')
    <script>
        document.querySelectorAll('.league-standings').forEach(function (block) {
            block.querySelectorAll('.league-tab').forEach(function (tab) {
                tab.addEventListener('click', function () {
                    var key = tab.dataset.league;
                    block.querySelectorAll('.league-tab').forEach(function (t) {
                        t.classList.remove('active');
                        t.setAttribute('aria-selected', 'false');
                    });
                    block.querySelectorAll('.league-panel').forEach(function (p) {
                        p.classList.remove('active');
                    });
                    tab.classList.add('active');
                    tab.setAttribute('aria-selected', 'true');
                    var panel = block.querySelector('[data-league-panel="' + key + '"]');
                    if (panel) panel.classList.add('active');
                });
            });
        });
    </script>
    @endpush
@endonce
