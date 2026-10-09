@extends('layouts.public', ['robots' => 'noindex, follow'])
@section('title', $phrase !== '' ? 'Suche: '.$phrase : 'Suche')
@php
    $labels = \App\Http\Controllers\Public\SearchController::LABELS;
    $tabs = collect($labels)->filter(fn ($label, $key) => ($counts[$key] ?? 0) > 0);
    $terms = [$phrase, ...$alternatives];
@endphp
@section('content')
<div class="search-header">
    <h1 class="{{ $phrase !== '' ? 'visually-hidden' : '' }}">{{ $phrase !== '' ? 'Suchergebnisse für „'.$phrase.'“' : 'Website durchsuchen' }}</h1>
    @include('public.partials.search-form', ['searchId' => 'results-search', 'searchValue' => $phrase, 'pill' => true, 'labelVisible' => true, 'searchLabel' => 'Ihre Suche', 'searchType' => $type !== '' ? $type : null])
    @if ($phrase !== '')
        <p class="search-summary">{{ $allTotal }} {{ $allTotal === 1 ? 'Ergebnis' : 'Ergebnisse' }}@if ($alternatives) · auch gesucht nach <strong>{{ implode(', ', $alternatives) }}</strong>@endif</p>
    @endif
</div>

@if ($phrase !== '' && $tabs->isNotEmpty())
    <nav class="type-tabs" aria-label="Ergebnisse nach Inhaltstyp">
        <ul>
            <li><a href="{{ route('public.search', ['q' => $phrase]) }}" @if ($type === '') aria-current="page" @endif>Alle <small>{{ $allTotal }}</small></a></li>
            @foreach ($tabs as $key => $label)
                <li><a href="{{ route('public.search', ['q' => $phrase, 'type' => $key]) }}" @if ($type === $key) aria-current="page" @endif>{{ $label }} <small>{{ $counts[$key] }}</small></a></li>
            @endforeach
        </ul>
    </nav>
@endif

@if ($phrase === '')
    <div class="empty-state">
        <h2>Wonach suchen Sie?</h2>
        <p>Geben Sie ein Anliegen oder einen Suchbegriff ein, zum Beispiel „Personalausweis“ oder „Sperrmüll“.</p>
        <a href="{{ app(\App\Services\Content\PublicCatalog::class)->sectionPath('az') }}">Leistungen von A bis Z ansehen</a>
    </div>
@elseif ($records->isEmpty())
    <div class="empty-state">
        <h2>Keine Ergebnisse gefunden</h2>
        <p>Zu „{{ $phrase }}“{{ $type !== '' ? ' in „'.($labels[$type] ?? $type).'“' : '' }} wurde nichts gefunden.</p>
        <ul>
            <li>Prüfen Sie die Schreibweise oder verwenden Sie einen kürzeren Begriff.</li>
            <li>Suchen Sie nach dem Anliegen, zum Beispiel „Ausweis“ statt „Personalausweisantrag“.</li>
            @if ($type !== '')<li><a href="{{ route('public.search', ['q' => $phrase]) }}">In allen Inhalten suchen</a></li>@endif
        </ul>
        <p><a href="{{ app(\App\Services\Content\PublicCatalog::class)->sectionPath('services') }}">Zum Bürgerservice</a> · <a href="{{ route('public.contact') }}">Frage an das Rathaus stellen</a></p>
    </div>
@else
    <ol class="result-list">
        @foreach ($records as $result)
            <li>
                <p class="result-type">{{ $labels[$result['type']] ?? 'Inhalt' }}</p>
                <h2><a href="{{ $result['url'] }}">{!! \App\Support\Content\PublicFormat::highlight($result['title'], $terms) !!}</a></h2>
                @if ($result['summary'])<p>{!! \App\Support\Content\PublicFormat::highlight(\Illuminate\Support\Str::limit($result['summary'], 220), $terms) !!}</p>@endif
                <p class="result-url">{{ parse_url($result['url'], PHP_URL_PATH) }}</p>
            </li>
        @endforeach
    </ol>
    @include('public.partials.pagination', ['paginator' => $records])
@endif
@endsection
