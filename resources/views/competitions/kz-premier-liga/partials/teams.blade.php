<section class="wc-section">
    <h2 class="wc-section-title">Командалар</h2>
  @if(!empty($teams))
    <div class="kpl-cards-grid">
        @foreach($teams as $team)
            <article class="kpl-card">
                <div class="kpl-card-head">
                    @if(!empty($team['logo']))
                        <img src="{{ $team['logo'] }}" alt="" class="kpl-card-logo" loading="lazy">
                    @else
                        <span class="kpl-card-logo kpl-card-logo--placeholder">⚽</span>
                    @endif
                    <h3 class="kpl-card-title">{{ $team['name'] }}</h3>
                </div>
                @if(!empty($team['venue']['name']))
                    <p class="kpl-card-meta">
                        <span>Стадион</span>
                        <strong>{{ $team['venue']['name'] }}</strong>
                    </p>
                @endif
                @if(!empty($team['venue']['city']))
                    <p class="kpl-card-meta">
                        <span>Қала</span>
                        <strong>{{ $team['venue']['city'] }}</strong>
                    </p>
                @endif
                @if(!empty($team['founded']))
                    <p class="kpl-card-meta">
                        <span>Негізделген</span>
                        <strong>{{ $team['founded'] }}</strong>
                    </p>
                @endif
            </article>
        @endforeach
    </div>
  @else
    <p class="wc-empty">Командалар тізімі жүктелуде…</p>
  @endif
</section>
