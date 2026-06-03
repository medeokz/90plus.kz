@if($articles->isNotEmpty())
<div class="more_news">
    @foreach($articles as $item)
        <a href="{{ route('articles.show', $item->slug) }}">
            <div class="news_image">
                @if($item->image_display)
                    <img src="{{ $item->image_display }}" alt="{{ $item->title_kk }}">
                @else
                    <span class="news_image-placeholder" aria-hidden="true">⚽</span>
                @endif
            </div>
            <div class="news_meta">
                @if($item->published_at)
                <p class="news_info">
                    <time datetime="{{ $item->published_at->toIso8601String() }}">{{ $item->published_at->diffForHumans() }}</time>
                </p>
                @endif
                <p class="news_title">{{ $item->title_kk }}</p>
            </div>
        </a>
    @endforeach
</div>
@endif
