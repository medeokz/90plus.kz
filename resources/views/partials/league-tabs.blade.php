    <div class="league-tabs" role="tablist">
        @foreach($leagues as $index => $league)
            @php $flagCode = $league['flag'] ?? $league['key']; @endphp
            <button type="button"
                    class="league-tab {{ $index === 0 ? 'active' : '' }}"
                    role="tab"
                    data-league="{{ $league['key'] }}"
                    aria-selected="{{ $index === 0 ? 'true' : 'false' }}">
                <span class="league-flag">
                    <img src="https://flagcdn.com/{{ $flagCode }}.svg"
                         alt="{{ $league['country'] ?? $league['name'] }}"
                         class="league-tab-flag"
                         loading="lazy">
                </span>
                <span class="league-tab-meta">
                    <span class="league-tab-label">{{ $league['short'] }}</span>
                    @if(empty($compact) && !empty($league['country']))
                        <span class="league-tab-country">{{ $league['country'] }}</span>
                    @endif
                </span>
            </button>
        @endforeach
    </div>
