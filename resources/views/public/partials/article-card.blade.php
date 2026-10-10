@php
    $image = $record->media->first(fn ($m) => $m->isPubliclyReachable() && $m->isImage() && $m->hasAccessibleAlternative());
    $crest = $image === null || preg_match('/wappen|beitragsbild_logo_merching/i', $image->original_filename.' '.$image->title);
    $municipalCrest = $image === null || preg_match('/beitragsbild_logo_merching|wappen-merching|wappen-alternativ/i', $image->original_filename);
    $tag = $headingTag ?? 'h2';
    $featured = $featured ?? false;
@endphp
<article class="article-card {{ $featured ? 'article-card--featured' : '' }} {{ $crest ? 'article-card--symbol' : '' }}">
    <div class="article-card__media {{ $crest ? 'news-preview-crest' : '' }}">
        @if ($image && ! $municipalCrest)
            @include('public.partials.image', ['medium' => $image, 'imageAlt' => '', 'imageClass' => $crest ? 'news-preview-crest__image' : '', 'imageSizes' => $featured ? '(max-width: 48rem) calc(100vw - 2rem), 44rem' : '(max-width: 48rem) calc(100vw - 2rem), 24rem', 'imageLoading' => $featured ? 'eager' : 'lazy'])
        @else
            <img class="news-preview-crest__image" src="{{ \Illuminate\Support\Facades\Vite::asset(config('public.wappen')) }}" alt="" width="667" height="693">
        @endif
    </div>
    <div class="article-card__body">
        <p class="meta"><time datetime="{{ $record->publish_at?->toIso8601String() }}">{{ \App\Support\SiteTime::formatLocalized($record->publish_at) }}</time>@if ($record->category) · {{ $record->category->name }}@endif</p>
        <{{ $tag }} class="article-card__title"><a href="{{ \App\Support\Routing\PublicPath::toUrl($record->publicPath()) }}">{{ $record->displayTitle() }}</a></{{ $tag }}>
        @if ($record->summary)<p class="article-card__summary">{{ $record->summary }}</p>@endif
    </div>
</article>
