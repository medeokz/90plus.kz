<section class="wc-section">
    <h2 class="wc-section-title">Топтық саты</h2>
    <div class="wc-groups-grid">
        @foreach($groups as $group)
            <div class="wc-group-card">
                <h3 class="wc-group-title">{{ $group['letter'] }} тобы</h3>
                <div class="league-table-wrap wc-group-table-wrap">
                    <table class="league-table wc-group-table">
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
                            @foreach($group['standings'] as $row)
                            <tr class="{{ $row['rank'] <= 2 ? 'row-top' : '' }}">
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
                                <td class="col-stat {{ $row['gd'] > 0 ? 'gd-pos' : ($row['gd'] < 0 ? 'gd-neg' : '') }}">
                                    {{ $row['gd'] > 0 ? '+' : '' }}{{ $row['gd'] }}
                                </td>
                                <td class="col-stat col-points"><strong>{{ $row['points'] }}</strong></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="wc-group-fixtures">
                    @foreach($group['fixtures'] as $fixture)
                        @include('competitions.world-cup.partials.fixture-row', ['fixture' => $fixture, 'compact' => true])
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</section>

@if(!empty($third_place))
<section class="wc-section">
    <h2 class="wc-section-title">3-орын командасының рейтингі</h2>
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
                    <th class="col-stat">±</th>
                    <th class="col-stat col-points">Ұ</th>
                </tr>
            </thead>
            <tbody>
                @foreach($third_place as $row)
                <tr>
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
                    <td class="col-stat">{{ $row['gd'] > 0 ? '+' : '' }}{{ $row['gd'] }}</td>
                    <td class="col-stat col-points"><strong>{{ $row['points'] }}</strong></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
@endif
