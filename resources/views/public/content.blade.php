{{-- Minimal, design-neutral rendering of a public record (temporary). --}}
@extends('layouts.public')

@php
    $attributes = $model->getAttributes();
    $archived = method_exists($model, 'isInPublicArchive') && $model->isInPublicArchive();
    $documents = method_exists($model, 'documents')
        ? $model->documents()->get()->filter(fn ($d) => $d->isPubliclyReachable())
        : collect();
    $links = method_exists($model, 'externalResources')
        ? $model->externalResources()->visible()->get()
        : collect();
@endphp

@section('title', $model->displayTitle())
@section('canonical', \App\Support\Routing\PublicPath::absoluteUrl((string) $model->publicPath()))

@section('content')
    <article>
        <h1>{{ $model->displayTitle() }}</h1>

        @if ($archived)
            <p class="notice">Archiv: Dieser Inhalt ist nicht mehr aktuell.</p>
        @endif

        @if ($model instanceof \App\Models\Event)
            <p>
                {{ $model->all_day ? \App\Support\SiteTime::format($model->starts_at, 'd.m.Y') : \App\Support\SiteTime::format($model->starts_at).' Uhr' }}
                @if ($model->ends_at && ! $model->all_day) bis {{ \App\Support\SiteTime::format($model->ends_at).' Uhr' }}@endif
            </p>
        @endif

        @if ($model instanceof \App\Models\Event)
            @if ($model->operational_status === \App\Enums\EventOperationalStatus::Cancelled)<p><strong>Abgesagt</strong></p>@endif
            @if ($model->schedule_notice)<p>{{ $model->schedule_notice }}</p>@endif
            @if ($model->location)@include('public.partials.location', ['location' => $model->location])@endif
        @endif
        @if ($model instanceof \App\Models\Location)@include('public.partials.location', ['location' => $model])@endif

        @if (! empty($attributes['summary']))
            <p>{{ $attributes['summary'] }}</p>
        @endif

        {{-- Editor text is rendered only through SafeMarkdown (escaped HTML, safe links, no images). --}}
        {!! \App\Support\Content\SafeMarkdown::toHtml($attributes['body'] ?? $attributes['description'] ?? null) !!}

        @if (method_exists($model, 'blocks'))@include('public.partials.blocks')@endif
        @if ($model instanceof \App\Models\Gallery)@include('public.partials.gallery', ['gallery' => $model])@endif
        @if ($model instanceof \App\Models\Service)@include('public.partials.service-details')@endif
        @if ($model instanceof \App\Models\CouncilTerm)@include('public.partials.council')@endif

        @if (method_exists($model,'media'))
            @foreach ($model->media()->get() as $medium)
                @if ($medium->isPubliclyReachable() && $medium->isImage() && $medium->hasAccessibleAlternative())
                    <figure>
                        <img src="{{ route('public.media',$medium->getKey()) }}" alt="{{ $medium->is_decorative ? '' : $medium->alt_text }}" width="{{ $medium->width }}" height="{{ $medium->height }}" loading="lazy">
                        @if ($medium->caption || $medium->copyright)<figcaption>{{ $medium->caption }}@if($medium->copyright) – {{ $medium->copyright }}@endif</figcaption>@endif
                    </figure>
                @endif
            @endforeach
        @endif

        @if ($documents->isNotEmpty())
            <h2>Dokumente</h2>
            <ul>
                @foreach ($documents as $document)
                    <li><a href="{{ \App\Support\Routing\PublicPath::toUrl($document->downloadPath()) }}">{{ $document->title }}</a>
                        ({{ strtoupper($document->extension) }}, {{ number_format($document->size_bytes / 1024, 0, ',', '.') }} KB)</li>
                @endforeach
            </ul>
        @endif

        @if ($links->isNotEmpty())
            <h2>Links</h2>
            <ul>
                @foreach ($links as $link)
                    <li><a href="{{ $link->url }}">{{ $link->title }}</a>@if ($link->provider_name) ({{ $link->provider_name }})@endif</li>
                @endforeach
            </ul>
        @endif
    </article>
@endsection
