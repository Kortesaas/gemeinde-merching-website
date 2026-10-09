@php
    $items = $gallery->items()->with('media')->get()->filter(function ($placement) {
        $medium = $placement->media;
        return $medium && $medium->isPubliclyReachable() && $medium->isImage() && ($medium->is_decorative || trim($placement->alternative($medium)) !== '');
    })->values();
@endphp
@if ($gallery->isPubliclyReachable() && $items->isNotEmpty())
<section class="gallery-block" aria-label="Bildergalerie: {{ $gallery->title }}">
    @if ($showTitle ?? true)<p class="gallery-block__title"><x-icon name="image" /> {{ $gallery->title }} <span class="meta">· {{ $items->count() }} {{ $items->count() === 1 ? 'Bild' : 'Bilder' }}</span></p>@endif
    <ul class="gallery">
        @foreach ($items as $placement)
            @php $medium = $placement->media; $caption = $placement->caption ?? $medium->caption; @endphp
            <li>
                <figure>
                    <a href="{{ route('public.media', $medium->getKey()) }}">@include('public.partials.image', ['medium' => $medium, 'imageAlt' => $placement->alternative($medium), 'imageSizes' => '(max-width: 40rem) 50vw, 16rem'])<span class="visually-hidden"> (Bild in Originalgröße öffnen)</span></a>
                    @if ($caption || $medium->copyright)<figcaption>{{ $caption }}@if ($caption && $medium->copyright) · @endif @if ($medium->copyright)<span class="copyright">© {{ $medium->copyright }}</span>@endif</figcaption>@endif
                </figure>
            </li>
        @endforeach
    </ul>
</section>
@endif
