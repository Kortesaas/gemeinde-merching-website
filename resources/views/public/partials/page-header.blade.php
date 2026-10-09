<header class="page-header">
    @if (! empty($eyebrow))<p class="eyebrow">{{ $eyebrow }}</p>@endif
    <h1>{{ $model->displayTitle() }}</h1>
    @if (! empty($lead))<p class="lead">{{ $lead }}</p>@endif
    {{ $slot ?? '' }}
</header>
