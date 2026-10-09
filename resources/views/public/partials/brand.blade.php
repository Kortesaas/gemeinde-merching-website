@php $brandTag ??= 'a'; @endphp
<{{ $brandTag }} class="brand" @if ($brandTag === 'a') href="{{ route('public.home') }}" @endif>
    <img class="brand__wappen" src="{{ \Illuminate\Support\Facades\Vite::asset(config('public.wappen')) }}" alt="" width="667" height="693">
    <span class="brand__text">
        <span class="brand__name">{{ $siteTitle ?? config('app.name') }}</span>
        @if (config('public.tagline'))<span class="brand__tagline">{{ config('public.tagline') }}</span>@endif
    </span>
</{{ $brandTag }}>
