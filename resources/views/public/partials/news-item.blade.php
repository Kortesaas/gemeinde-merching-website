@php
    $record->loadMissing(['category', 'canonicalRoute']);
    $image = ($withImage ?? false) ? $record->media->first(fn ($m) => $m->isPubliclyReachable() && $m->isImage() && $m->hasAccessibleAlternative()) : null;
    $crest = $image === null || preg_match('/wappen|beitragsbild_logo_merching/i', $image->original_filename.' '.$image->title);
    $municipalCrest = $image === null || preg_match('/beitragsbild_logo_merching|wappen-merching|wappen-alternativ/i', $image->original_filename);
    $tag = $headingTag ?? 'h2';
@endphp
<article class="news-item {{ ($withImage ?? false) ? 'news-item--lead' : '' }}">
    @if ($withImage ?? false)
        <div class="news-item__media {{ $crest ? 'news-preview-crest' : '' }}">@if ($municipalCrest)<img class="news-preview-crest__image" src="{{ \Illuminate\Support\Facades\Vite::asset(config('public.wappen')) }}" alt="" width="667" height="693">@else @include('public.partials.image', ['medium' => $image, 'imageClass' => $crest ? 'news-preview-crest__image' : '', 'imageSizes' => '(max-width: 64rem) calc(100vw - 2rem), 36rem', 'imageAlt' => '']) @endif</div>
    @endif
    <p class="meta"><time datetime="{{ $record->publish_at?->toIso8601String() }}">{{ \App\Support\SiteTime::formatLocalized($record->publish_at) }}</time>@if ($record->category) · {{ $record->category->name }}@endif</p>
    <{{ $tag }} class="news-item__title"><a href="{{ \App\Support\Routing\PublicPath::toUrl((string) $record->publicPath()) }}">{{ $record->displayTitle() }}</a></{{ $tag }}>
    @if ($record->summary)<p class="news-item__summary">{{ $record->summary }}</p>@endif
</article>
