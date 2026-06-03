<div
    class="reaction xl-mt-20 xl-mb-20"
    data-reaction-block
    data-endpoint="{{ route('articles.reactions.store', $article->slug) }}"
    data-selected="{{ $reactionSelected ?? '' }}"
    data-csrf="{{ csrf_token() }}"
>
    <h3 class="reaction__title">Сіздің реакцияңыз?</h3>
    <div class="reaction__list">
        <button type="button" class="reaction__item" data-reaction-item data-reaction="like">
            <span class="reaction__label">Ұнайды</span>
            <strong class="reaction__count" data-count>{{ $reactionCounts['like'] ?? 0 }}</strong>
        </button>
        <button type="button" class="reaction__item" data-reaction-item data-reaction="dislike">
            <span class="reaction__label">Ұнамайды</span>
            <strong class="reaction__count" data-count>{{ $reactionCounts['dislike'] ?? 0 }}</strong>
        </button>
        <button type="button" class="reaction__item" data-reaction-item data-reaction="funny">
            <span class="reaction__label">Күлкілі</span>
            <strong class="reaction__count" data-count>{{ $reactionCounts['funny'] ?? 0 }}</strong>
        </button>
        <button type="button" class="reaction__item" data-reaction-item data-reaction="angry">
            <span class="reaction__label">Масқара</span>
            <strong class="reaction__count" data-count>{{ $reactionCounts['angry'] ?? 0 }}</strong>
        </button>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var block = document.querySelector('[data-reaction-block]');
    if (!block) return;

    var endpoint = block.getAttribute('data-endpoint');
    var csrf = block.getAttribute('data-csrf');
    var selected = block.getAttribute('data-selected') || '';
    var items = Array.from(block.querySelectorAll('[data-reaction-item]'));

    function paintActive(value) {
        items.forEach(function (item) {
            item.classList.toggle('is-active', item.getAttribute('data-reaction') === value);
        });
    }

    function updateCounts(counts) {
        items.forEach(function (item) {
            var key = item.getAttribute('data-reaction');
            var target = item.querySelector('[data-count]');
            if (target && counts && Object.prototype.hasOwnProperty.call(counts, key)) {
                target.textContent = String(counts[key]);
            }
        });
    }

    paintActive(selected);

    items.forEach(function (item) {
        item.addEventListener('click', function () {
            var reaction = item.getAttribute('data-reaction');
            if (!reaction || !endpoint) return;

            items.forEach(function (btn) { btn.disabled = true; });

            fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf
                },
                body: JSON.stringify({ reaction: reaction })
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data && data.ok) {
                    selected = data.selected || reaction;
                    paintActive(selected);
                    updateCounts(data.counts || {});
                }
            })
            .catch(function () {})
            .finally(function () {
                items.forEach(function (btn) { btn.disabled = false; });
            });
        });
    });
});
</script>
@endpush
