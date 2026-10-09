@if ($gallery->isPubliclyReachable())
    @foreach ($gallery->items()->with('media')->get() as $placement)
        @php $medium = $placement->media; @endphp
        @if ($medium && $medium->isPubliclyReachable() && $medium->isImage() && ($medium->is_decorative || trim($placement->alternative($medium)) !== ''))
            <figure>
                <img src="{{ route('public.media', $medium->getKey()) }}" alt="{{ $placement->alternative($medium) }}" width="{{ $medium->width }}" height="{{ $medium->height }}" loading="lazy">
                @if ($placement->caption ?? $medium->caption)<figcaption>{{ $placement->caption ?? $medium->caption }}@if ($medium->copyright) – {{ $medium->copyright }}@endif</figcaption>
                @elseif ($medium->copyright)<figcaption>{{ $medium->copyright }}</figcaption>@endif
            </figure>
        @endif
    @endforeach
@endif
