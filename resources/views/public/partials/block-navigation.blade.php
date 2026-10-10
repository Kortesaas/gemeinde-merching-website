@php
    $navigationItems = collect($group['items'])->map(function ($item) {
        $html = \App\Support\Content\SafeMarkdown::toHtml($item['block']->text);
        preg_match('~href="([^"]+)"~', $html, $match);
        return ['html' => $html, 'path' => html_entity_decode($match[1], ENT_QUOTES, 'UTF-8')];
    });
    $galleryRoutes = \App\Models\PublicRoute::query()->where('routable_type', 'gallery')->where('is_active', true)->whereIn('path', $navigationItems->pluck('path'))->with('routable.items.media')->get()->keyBy('path');
@endphp
<ul class="block-navigation {{ $galleryRoutes->isNotEmpty() ? 'block-navigation--galleries' : '' }}">
    @foreach ($navigationItems as $item)
        @php
            $gallery = $galleryRoutes->get($item['path'])?->routable;
            $preview = $gallery?->isPubliclyReachable() ? $gallery->items->first(fn ($placement) => $placement->media && $placement->media->isPubliclyReachable() && $placement->media->isImage())?->media : null;
        @endphp
        <li>
            @if ($preview)<div class="navigation-preview" aria-hidden="true">@include('public.partials.image', ['medium' => $preview, 'imageAlt' => '', 'imageSizes' => '(max-width: 40rem) calc(100vw - 2rem), 24rem'])</div>@endif
            {!! $item['html'] !!}
        </li>
    @endforeach
</ul>
