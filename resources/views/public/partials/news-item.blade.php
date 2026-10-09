@php
    $record->loadMissing(['category', 'canonicalRoute']);
    $image = ($withImage ?? false) ? $record->media->first(fn ($m) => $m->isPubliclyReachable() && $m->isImage() && $m->hasAccessibleAlternative()) : null;
    $tag = $headingTag ?? 'h2';
@endphp
<article class="news-item {{ $image ? 'news-item--lead' : '' }}">
    @if ($image)
        <div class="news-item__media">@include('public.partials.image', ['medium' => $image, 'imageSizes' => '(max-width: 64rem) calc(100vw - 2rem), 36rem', 'imageAlt' => ''])</div>
    @endif
    <p class="meta"><time datetime="{{ $record->publish_at?->toIso8601String() }}">{{ \App\Support\SiteTime::formatLocalized($record->publish_at) }}</time>@if ($record->category) · {{ $record->category->name }}@endif</p>
    <{{ $tag }} class="news-item__title"><a href="{{ \App\Support\Routing\PublicPath::toUrl((string) $record->publicPath()) }}">{{ $record->displayTitle() }}</a></{{ $tag }}>
    @if ($record->summary)<p class="news-item__summary">{{ $record->summary }}</p>@endif
</article>
