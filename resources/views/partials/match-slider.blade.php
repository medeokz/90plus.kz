@if(!empty($matchSliderFixtures))
<div class="match-slider" data-start="{{ $matchSliderStart ?? 0 }}" data-total="{{ count($matchSliderFixtures) }}">
    <div class="match-slider__viewport swiper">
            <div class="match-slider__track swiper-wrapper">
            @foreach($matchSliderFixtures as $index => $match)
            <div class="match-slider__slide swiper-slide" data-num="{{ $index + 1 }}" title="{{ $match['title'] }}">
                <div class="match-slider__border" aria-hidden="true"></div>
                @if(!empty($match['status_bar_color']))
                <div class="match-slider__status-bar" style="width: {{ $match['status_bar_width'] ?? '100%' }}; background-color: {{ $match['status_bar_color'] }};"></div>
                @endif
                <a href="{{ $match['url'] }}" class="match-slider__link">
                    <div class="match-slider__card" data-mid="{{ $match['id'] }}">
                        <div class="match-slider__status match-slider__status--{{ $match['status_class'] }}">
                            {!! $match['status_html'] !!}
                        </div>
                        <div class="match-slider__teams">
                            <div class="match-slider__team match-slider__team--home">
                                <div class="match-slider__logo">
                                    @if(!empty($match['home_flag']))
                                    <img src="{{ $match['home_flag'] }}" alt="" width="14" height="14" loading="lazy">
                                    @endif
                                </div>
                                <div class="match-slider__name" title="{{ $match['home_team'] }}">{{ $match['home_short'] }}</div>
                                <div class="match-slider__score">{{ $match['home_score_display'] }}</div>
                            </div>
                            <div class="match-slider__team match-slider__team--away">
                                <div class="match-slider__logo">
                                    @if(!empty($match['away_flag']))
                                    <img src="{{ $match['away_flag'] }}" alt="" width="14" height="14" loading="lazy">
                                    @endif
                                </div>
                                <div class="match-slider__name" title="{{ $match['away_team'] }}">{{ $match['away_short'] }}</div>
                                <div class="match-slider__score">{{ $match['away_score_display'] }}</div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
            @endforeach
            </div>
    </div>
    <button type="button" class="match-slider__nav match-slider__nav--prev" aria-label="Алдыңғы"></button>
    <button type="button" class="match-slider__nav match-slider__nav--next" aria-label="Келесі"></button>
</div>
@endif
