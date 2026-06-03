<a href="{{ route('clubs.show', $club->slug) }}" class="club-card">
    @if($club->logo_url)
        <img src="{{ $club->logo_url }}" alt="{{ $club->name }}" loading="lazy" width="48" height="48" class="icon-club">
    @else
        <span class="club-card__placeholder" aria-hidden="true">{{ mb_substr($club->name, 0, 1) }}</span>
    @endif
    <span class="club-card__info">
        <span class="club-card__name">{{ $club->name }}</span>
        @if(!empty($showCountry) && ($club->countryRecord?->flag_url || $club->country))
            <span class="club-card__country">
                @if($club->countryRecord?->flag_url)
                    <img src="{{ $club->countryRecord->flag_url }}" alt="" loading="lazy" width="20" height="14" class="icon-flag">
                @endif
                {{ $club->countryRecord?->name ?? $club->country }}
            </span>
        @endif
    </span>
</a>
