<section class="article-comments" id="comments">
    <h2 class="article-comments__title">Пікірлер <span class="article-comments__count">{{ $comments->count() }}</span></h2>

    @if(session('comment_success'))
        <p class="article-comments__notice article-comments__notice--success">Пікіріңіз жарияланды. Рахмет!</p>
    @endif

    @if($errors->any())
        <div class="article-comments__notice article-comments__notice--error">
            @foreach($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form class="article-comments__form" method="POST" action="{{ route('articles.comments.store', $article->slug) }}">
        @csrf
        <div class="article-comments__fields">
            <label class="article-comments__field">
                <span>Атыңыз</span>
                <input type="text" name="author_name" value="{{ old('author_name') }}" maxlength="80" required placeholder="Мысалы: Айбек">
            </label>
            <label class="article-comments__field article-comments__field--full">
                <span>Пікір</span>
                <textarea name="body" rows="4" maxlength="2000" required placeholder="Пікіріңізді жазыңыз...">{{ old('body') }}</textarea>
            </label>
        </div>
        <button type="submit" class="article-comments__submit">Жіберу</button>
    </form>

    @if($comments->isEmpty())
        <p class="article-comments__empty">Әзірше пікір жоқ. Бірінші болыңыз!</p>
    @else
        <ul class="article-comments__list">
            @foreach($comments as $comment)
                <li class="article-comment">
                    <div class="article-comment__head">
                        <strong class="article-comment__author">{{ $comment->author_name }}</strong>
                        <time datetime="{{ $comment->created_at->toIso8601String() }}">
                            {{ $comment->created_at->diffForHumans() }}
                        </time>
                    </div>
                    <p class="article-comment__body">{{ $comment->body }}</p>
                </li>
            @endforeach
        </ul>
    @endif
</section>
