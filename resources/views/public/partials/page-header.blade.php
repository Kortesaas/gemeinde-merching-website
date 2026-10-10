@php $withCrest = in_array($model->publicPath(), ['/ortsrecht', '/gemeindekurier'], true); @endphp
<header class="page-header {{ $withCrest ? 'page-header--crest' : '' }}">
    @if ($withCrest)<img class="page-header__crest" src="{{ \Illuminate\Support\Facades\Vite::asset(config('public.wappen')) }}" alt="" width="667" height="693">@endif
    @if (! empty($eyebrow))<p class="eyebrow">{{ $eyebrow }}</p>@endif
    <h1>{{ $model->displayTitle() }}</h1>
    @if (! empty($lead))<p class="lead">{{ $lead }}</p>@endif
    {{ $slot ?? '' }}
</header>
