@extends('layouts.public')
@section('title', ($model ?? null)?->displayTitle() ?? $section['title'])
@php
    $catalog = app(\App\Services\Content\PublicCatalog::class);
    $leads = [
        'services' => 'Leistungen der Gemeindeverwaltung, Formulare und die richtigen Ansprechpersonen – vieles auch online.',
        'az' => 'Alle Anliegen von A bis Z – mit der zuständigen Stelle und der direkten Durchwahl.',
        'articles' => 'Meldungen aus dem Rathaus und der Gemeinde.',
        'events' => 'Termine von Gemeinde, Vereinen und Einrichtungen.',
        'notices' => 'Amtliche Bekanntmachungen der Gemeinde.',
        'budgets' => 'Haushaltspläne und Anlagen in ihrer veröffentlichten Reihenfolge zum Herunterladen.',
        'documents' => 'Formulare, Satzungen, Merkblätter und Pläne zum Herunterladen.',
        'directory' => 'Verwaltung, Ansprechpersonen, Einrichtungen und Vereine.',
        'organizations' => 'Vereine, Initiativen, Gastronomie und Betriebe in der Gemeinde.',
    ];
    $archive = request()->boolean('archiv');
@endphp
@section('content')
<header class="page-header">
    <h1>{{ ($model ?? null)?->displayTitle() ?? $section['title'] }}@if ($archive) <span class="badge badge--archive">Archiv</span>@endif</h1>
    <p class="lead">{{ ($model ?? null)?->summary ?: $leads[$kind] }}</p>
</header>
@isset($model)
    @if ($model->body || $model->blocks()->exists())
        <div class="catalog-intro">
            @if ($model->body)<div class="prose">{!! \App\Support\Content\SafeMarkdown::toHtml($model->body) !!}</div>@endif
            @include('public.partials.blocks')
        </div>
    @endif
@endisset
@include('public.catalog.'.$kind)
@endsection
