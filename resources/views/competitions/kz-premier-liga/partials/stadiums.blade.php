<section class="wc-section">
    <h2 class="wc-section-title">Стадиондар</h2>
  @if(!empty($stadiums))
    <div class="kpl-cards-grid">
        @foreach($stadiums as $stadium)
            <article class="kpl-card">
                @if(!empty($stadium['image']))
                    <img src="{{ $stadium['image'] }}" alt="{{ $stadium['name'] }}" class="kpl-stadium-img" loading="lazy">
                @endif
                <h3 class="kpl-card-title">{{ $stadium['name'] }}</h3>
                @if(!empty($stadium['city']))
                    <p class="kpl-card-meta">
                        <span>Қала</span>
                        <strong>{{ $stadium['city'] }}</strong>
                    </p>
                @endif
                @if(!empty($stadium['capacity']))
                    <p class="kpl-card-meta">
                        <span>Сыйымдылық</span>
                        <strong>{{ number_format((int) $stadium['capacity'], 0, '', ' ') }}</strong>
                    </p>
                @endif
                @if(!empty($stadium['address']))
                    <p class="kpl-card-address">{{ $stadium['address'] }}</p>
                @endif
                @if(!empty($stadium['teams']))
                    <p class="kpl-card-teams">{{ implode(', ', $stadium['teams']) }}</p>
                @endif
            </article>
        @endforeach
    </div>
  @else
    <p class="wc-empty">Стадиондар туралы деректер жоқ. API синхрондауын күтіңіз.</p>
  @endif
</section>
