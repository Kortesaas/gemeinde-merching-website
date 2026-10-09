@php
    $image = $record->media->first(fn ($m) => $m->isPubliclyReachable() && $m->isImage() && $m->hasAccessibleAlternative());
    $tag = $headingTag ?? 'h2';
    $featured = $featured ?? false;
@endphp
<article class="article-card {{ $featured ? 'article-card--featured' : '' }}">
    <div class="article-card__media">
        @if ($image)
            @include('public.partials.image', ['medium' => $image, 'imageAlt' => '', 'imageSizes' => $featured ? '(max-width: 48rem) calc(100vw - 2rem), 44rem' : '(max-width: 48rem) calc(100vw - 2rem), 24rem', 'imageLoading' => $featured ? 'eager' : 'lazy'])
        @else
            <div class="article-card__placeholder" aria-hidden="true"><x-icon name="file" /><span>{{ $record->category?->name ?? 'Meldung' }}</span></div>
        @endif
    </div>
    <div class="article-card__body">
        <p class="meta"><time datetime="{{ $record->publish_at?->toIso8601String() }}">{{ \App\Support\SiteTime::formatLocalized($record->publish_at) }}</time>@if ($record->category) · {{ $record->category->name }}@endif</p>
        <{{ $tag }} class="article-card__title"><a href="{{ \App\Support\Routing\PublicPath::toUrl($record->publicPath()) }}">{{ $record->displayTitle() }}</a></{{ $tag }}>
        @if ($record->summary)<p class="article-card__summary">{{ $record->summary }}</p>@endif
    </div>
</article>
