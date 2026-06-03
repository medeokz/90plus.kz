@if($sliderItems->isNotEmpty())
<div class="slider-media playing" id="slider-media">
    <div class="swiper slider-media__swiper slider-media_main">
        <div class="swiper-wrapper">
            @foreach($sliderItems as $item)
            <div class="swiper-slide slider-media__slide">
                <div class="slider-media__slide-wrapper">
                    <a href="{{ route('articles.show', $item->slug) }}">
                        <div class="slider-media__slide-bg{{ $item->image_url ? '' : ' slider-media__slide-bg--empty' }}"
                             @if($item->image_url) style="background-image:url('{{ $item->image_display }}');" @endif></div>
                        <div class="slider-media__slide-content">
                            <div class="slider-media__slide-title">{{ $item->title_kk }}</div>
                            <div class="slider-media__slide-desk">{{ $item->summary_kk }}</div>
                        </div>
                    </a>
                </div>
            </div>
            @endforeach
        </div>
    </div>

    @if($sliderItems->count() > 1)
    <div class="swiper slider-media__swiper-thumbs slider-media_thumbs">
        <div class="swiper-wrapper">
            @foreach($sliderItems as $item)
            <div class="swiper-slide">
                <div class="swiper-slide__progress-bar progress-bar">
                    <div class="progress-bar__fill"></div>
                </div>
                <div class="slider-media__slide-content">
                    <div class="slider-media__slide-title">{{ $item->title_kk }}</div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif
</div>
@endif
