<figure class="block-image {{ $medium->height > $medium->width ? 'block-image--portrait' : '' }}">
    @include('public.partials.image', ['medium' => $medium, 'imageSizes' => min(800, $medium->width).'px'])
    @if ($medium->caption || $medium->copyright)<figcaption>{{ $medium->caption }}@if ($medium->caption && $medium->copyright) · @endif @if ($medium->copyright)<span class="copyright">© {{ $medium->copyright }}</span>@endif</figcaption>@endif
</figure>
