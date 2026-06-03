@extends('layouts.app')

@section('title', $fixture->home_team . ' — ' . $fixture->away_team . ' — ' . config('app.name'))

@push('head')
    <link rel="stylesheet" href="{{ asset('css/fixture-page.css') }}">
@endpush

@section('content')
<div class="fixture-page">
    <div class="container">
        <nav class="fixture-breadcrumb">
            <a href="{{ route('home') }}">Басты бет</a>
            <span>›</span>
            <a href="{{ route('fixtures.index') }}">Матчтар</a>
            <span>›</span>
            <span>{{ $fixture->home_team }} — {{ $fixture->away_team }}</span>
        </nav>

        <div class="fixture-scoreboard">
            <div class="fixture-competition">{{ $fixture->competition }}</div>
            <div class="fixture-score-main">
                <div class="fixture-team fixture-team--home">
                    @if($fixture->home_team_flag)
                        <img src="{{ $fixture->home_team_flag }}" alt="{{ $fixture->home_team }}" class="fixture-flag">
                    @endif
                    <span class="fixture-team-name">{{ $fixture->home_team }}</span>
                </div>
                <div class="fixture-score-center">
                    <div class="fixture-score-numbers">{{ $fixture->home_score }} : {{ $fixture->away_score }}</div>
                    <div class="fixture-status">{{ $fixture->statusLabel() }}</div>
                </div>
                <div class="fixture-team fixture-team--away">
                    @if($fixture->away_team_flag)
                        <img src="{{ $fixture->away_team_flag }}" alt="{{ $fixture->away_team }}" class="fixture-flag">
                    @endif
                    <span class="fixture-team-name">{{ $fixture->away_team }}</span>
                </div>
            </div>
            @if($fixture->kickoff_at)
                <div class="fixture-kickoff">{{ $fixture->kickoff_at->format('d.m.Y, H:i') }}</div>
            @endif
        </div>

        @if(!empty($fixture->events))
        <section class="fixture-card">
            <h2 class="fixture-card-title">Ойын оқиғалары</h2>
            <div class="fixture-events">
                @foreach($fixture->events as $event)
                <div class="fixture-event fixture-event--{{ $event['team'] ?? 'home' }}">
                    <span class="fixture-event-minute">{{ $event['minute'] }}@if(!empty($event['extra']))+{{ $event['extra'] }}@endif'</span>
                    <span class="fixture-event-icon">{{ $event['icon'] ?? '⚽' }}</span>
                    <div class="fixture-event-body">
                        <strong>{{ $event['player'] }}</strong>
                        @if(!empty($event['assist']))
                            <small>({{ $event['assist'] }})</small>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
        </section>
        @endif

        @php
            $periods = collect($fixture->statistics['periods'] ?? [])->filter(fn ($rows) => ! empty($rows));
            $periodLabels = ['full' => 'Бүкіл ойын', '1h' => '1-тайм', '2h' => '2-тайм'];
            $defaultPeriod = $periods->has('full') ? 'full' : $periods->keys()->first();
        @endphp
        @if($periods->isNotEmpty())
        <section class="fixture-card">
            <h2 class="fixture-card-title">Ойын статистикасы</h2>
            @if($periods->count() > 1)
            <div class="fixture-stat-tabs" data-stat-tabs>
                @foreach($periodLabels as $periodKey => $periodLabel)
                    @if($periods->has($periodKey))
                    <button type="button" class="{{ $periodKey === $defaultPeriod ? 'active' : '' }}" data-period="{{ $periodKey }}">{{ $periodLabel }}</button>
                    @endif
                @endforeach
            </div>
            @endif
            @foreach($periods as $periodKey => $stats)
            <div class="fixture-stat-panel {{ $periodKey === $defaultPeriod ? 'active' : '' }}" data-period-panel="{{ $periodKey }}">
                @foreach($stats as $stat)
                @php
                    $home = (float) ($stat['home'] ?? 0);
                    $away = (float) ($stat['away'] ?? 0);
                    $isPercent = ! empty($stat['percent']);
                    $total = $isPercent ? 100 : ($home + $away);
                    $homePct = $total > 0 ? round(($home / $total) * 100) : 50;
                    $awayPct = 100 - $homePct;
                    $homeDisplay = is_float($home) && floor($home) != $home ? number_format($home, 2) : (int) $home;
                    $awayDisplay = is_float($away) && floor($away) != $away ? number_format($away, 2) : (int) $away;
                    if ($isPercent) {
                        $homeDisplay .= '%';
                        $awayDisplay .= '%';
                    }
                @endphp
                <div class="fixture-stat-row">
                    <span class="fixture-stat-val">{{ $homeDisplay }}</span>
                    <div class="fixture-stat-bar-wrap">
                        <span class="fixture-stat-label">{{ $stat['label'] }}</span>
                        <div class="fixture-stat-bar">
                            <div class="fixture-stat-bar-home" style="width: {{ $homePct }}%"></div>
                            <div class="fixture-stat-bar-away" style="width: {{ $awayPct }}%"></div>
                        </div>
                    </div>
                    <span class="fixture-stat-val">{{ $awayDisplay }}</span>
                </div>
                @endforeach
            </div>
            @endforeach
        </section>
        @endif

        @if(!empty($fixture->lineups))
        <section class="fixture-card">
            <h2 class="fixture-card-title">Бастапқы құрамдар</h2>
            <div class="fixture-lineups">
                @foreach(['home' => $fixture->home_team, 'away' => $fixture->away_team] as $side => $teamName)
                @php $lineup = $fixture->lineups[$side] ?? null; @endphp
                @if($lineup)
                <div class="fixture-lineup-col">
                    <h3>{{ $teamName }}</h3>
                    @if(!empty($lineup['coach']))
                        <p class="fixture-coach">Жаттықтырушы: {{ $lineup['coach'] }}</p>
                    @endif
                    <ul class="fixture-players">
                        @foreach($lineup['starting'] ?? [] as $player)
                        <li><span class="player-num">{{ $player['number'] ?? '' }}</span> {{ $player['name'] }}</li>
                        @endforeach
                    </ul>
                    @if(!empty($lineup['subs']))
                    <h4>Қосалқы</h4>
                    <ul class="fixture-players fixture-players--subs">
                        @foreach($lineup['subs'] as $player)
                        <li><span class="player-num">{{ $player['number'] ?? '' }}</span> {{ $player['name'] }}</li>
                        @endforeach
                    </ul>
                    @endif
                </div>
                @endif
                @endforeach
            </div>
        </section>
        @endif

        <div class="fixture-grid-2">
            <section class="fixture-card">
                <h2 class="fixture-card-title">Матч туралы</h2>
                <dl class="fixture-info-list">
                    @if($fixture->venue)<dt>Стадион</dt><dd>{{ $fixture->venue }}</dd>@endif
                    @if($fixture->city)<dt>Қала</dt><dd>{{ $fixture->city }}</dd>@endif
                    @if($fixture->temperature || $fixture->weather)
                        <dt>Ауа-райы</dt>
                        <dd>{{ $fixture->temperature }} {{ $fixture->weather }}</dd>
                    @endif
                    @if($fixture->broadcast)<dt>Трансляция</dt><dd>{{ $fixture->broadcast }}</dd>@endif
                    @if(!empty($fixture->referees))
                        <dt>Терр</dt><dd>{{ implode(' · ', $fixture->referees) }}</dd>
                    @endif
                </dl>
            </section>

            @if(!empty($fixture->team_form))
            <section class="fixture-card">
                <h2 class="fixture-card-title">Команда формасы</h2>
                @foreach(['home' => $fixture->home_team, 'away' => $fixture->away_team] as $side => $teamName)
                @php $form = $fixture->team_form[$side] ?? null; @endphp
                @if($form)
                <div class="fixture-form-block">
                    <h3>{{ $teamName }} <span class="form-badges">{{ $form['form'] ?? '' }}</span></h3>
                    @if(!empty($form['summary']))
                    <div class="form-summary">
                        <span>{{ $form['summary']['matches'] ?? 0 }} матч</span>
                        <span>{{ $form['summary']['goals'] ?? 0 }} гол</span>
                        <span>{{ $form['summary']['wins'] ?? 0 }}W / {{ $form['summary']['draws'] ?? 0 }}D / {{ $form['summary']['losses'] ?? 0 }}L</span>
                    </div>
                    @endif
                    @if(!empty($form['recent']))
                    <ul class="form-recent">
                        @foreach($form['recent'] as $match)
                        <li><time>{{ $match['date'] }}</time> {{ $match['text'] }}</li>
                        @endforeach
                    </ul>
                    @endif
                </div>
                @endif
                @endforeach
            </section>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.querySelectorAll('[data-stat-tabs]').forEach(tabs => {
    const buttons = tabs.querySelectorAll('button[data-period]');
    const panels = tabs.parentElement.querySelectorAll('[data-period-panel]');
    buttons.forEach(btn => {
        btn.addEventListener('click', () => {
            const period = btn.dataset.period;
            buttons.forEach(b => b.classList.toggle('active', b === btn));
            panels.forEach(p => p.classList.toggle('active', p.dataset.periodPanel === period));
        });
    });
});
</script>
@endpush
