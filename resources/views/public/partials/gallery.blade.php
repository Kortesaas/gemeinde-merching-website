@if ($gallery->isPubliclyReachable())
<div class="gallery" aria-label="{{ $gallery->title }}">
    @foreach ($gallery->items()->with('media')->get() as $placement)
        @php $medium=$placement->media; @endphp
        @if ($medium && $medium->isPubliclyReachable() && $medium->isImage() && ($medium->is_decorative || trim($placement->alternative($medium)) !== ''))
            <figure>
                <a href="{{ route('public.media', $medium->getKey()) }}" @if($medium->is_decorative) aria-label="Bild in Originalgröße öffnen" @endif>@include('public.partials.image', ['imageAlt'=>$placement->alternative($medium), 'imageSizes'=>'24rem'])</a>
                @if ($placement->caption ?? $medium->caption)<figcaption>{{ $placement->caption ?? $medium->caption }} @if ($medium->copyright) – {{ $medium->copyright }} @endif</figcaption>@elseif ($medium->copyright)<figcaption>{{ $medium->copyright }}</figcaption>@endif
            </figure>
        @endif
    @endforeach
</div>
@endif
