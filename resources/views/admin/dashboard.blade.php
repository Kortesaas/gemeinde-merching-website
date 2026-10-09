@extends('layouts.admin')
@section('title', 'Dashboard')
@php
    $actions = collect(['article' => 'Neue Meldung', 'event' => 'Neuer Termin', 'document' => 'Dokument hochladen'])
        ->filter(fn ($label, $key) => auth()->user()->can('create', \App\Admin\ResourceRegistry::get($key)->model()));
    $edit = fn ($row) => route('admin.'.$row['resource']->key().'.edit', $row['record']->getKey());
@endphp
@section('content')
<header class="cms-page-header">
    <div>
        <p class="cms-eyebrow">{{ \App\Support\SiteTime::formatLocalized(now(), 'l, j. F Y') }}</p>
        <h1>Was benötigt Ihre Aufmerksamkeit?</h1>
    </div>
    @if ($actions->isNotEmpty())
        <div class="cms-page-actions">
            @foreach ($actions as $key => $label)
                <a class="button {{ $loop->first ? '' : 'button--secondary' }}" href="{{ route('admin.'.$key.'.create') }}">{{ $label }}</a>
            @endforeach
        </div>
    @endif
</header>
<x-status />

<ul class="stat-tiles" aria-label="Kennzahlen">
    <li><a href="{{ route('admin.proposals.index') }}"><strong>{{ $counts['review'] }}</strong><span>{{ $counts['review'] === 1 ? 'Vorschlag wartet' : 'Vorschläge warten' }} auf Ihre Freigabe</span></a></li>
    <li><a href="#geplant"><strong>{{ $counts['scheduled'] }}</strong><span>geplante Veröffentlichungen</span></a></li>
    <li><a href="#geplant"><strong>{{ $counts['expiring'] }}</strong><span>Inhalte laufen in 14 Tagen ab</span></a></li>
    <li><a href="#qualitaet" class="{{ $counts['unchecked'] > 0 ? 'is-warning' : '' }}"><strong>{{ $counts['unchecked'] }}</strong><span>Dokumente ohne geprüfte Barrierefreiheit</span></a></li>
</ul>

<div class="cms-grid cms-grid--2">
    <section class="cms-panel" aria-labelledby="review-heading">
        <header class="cms-panel__header"><h2 id="review-heading">Wartet auf Ihre Freigabe</h2><a href="{{ route('admin.proposals.index') }}">Alle</a></header>
        <ul class="cms-rows">
            @forelse ($review as $proposal)
                <li class="cms-row">
                    <span class="state state--review">In Prüfung</span>
                    <div class="cms-row__body">
                        <a class="cms-row__title" href="{{ route('admin.proposals.show', $proposal) }}">{{ $proposal->proposable?->displayTitle() ?? 'Inhalt' }}</a>
                        <p class="cms-row__meta">{{ \App\Admin\ResourceRegistry::forModel($proposal->proposable)?->label() }} · von {{ $proposal->author?->name ?? 'unbekannt' }} · {{ $proposal->submitted_at?->locale('de')->diffForHumans() }}@if ($proposal->summary) · „{{ $proposal->summary }}“@endif</p>
                    </div>
                    <a class="button button--secondary button--small" href="{{ route('admin.proposals.show', $proposal) }}">Prüfen<span class="visually-hidden">: {{ $proposal->proposable?->displayTitle() }}</span></a>
                </li>
            @empty
                <li class="cms-empty">Zurzeit warten keine Vorschläge auf Ihre Freigabe.</li>
            @endforelse
        </ul>
    </section>

    <section class="cms-panel" aria-labelledby="geplant-heading" id="geplant">
        <header class="cms-panel__header"><h2 id="geplant-heading">Geplant und bald ablaufend</h2></header>
        <ul class="cms-rows">
            @forelse ($attention as $row)
                <li class="cms-row">
                    <span class="state {{ $row['kind'] === 'scheduled' ? 'state--scheduled' : 'state--expiring' }}">{{ $row['kind'] === 'scheduled' ? 'Geplant' : 'Läuft ab' }}</span>
                    <div class="cms-row__body">
                        <a class="cms-row__title" href="{{ $edit($row) }}">{{ $row['record']->displayTitle() }}</a>
                        <p class="cms-row__meta">{{ $row['resource']->label() }} · {{ $row['kind'] === 'scheduled' ? 'erscheint am' : 'endet am' }} {{ \App\Support\SiteTime::format($row['at']) }}</p>
                    </div>
                </li>
            @empty
                <li class="cms-empty">In den nächsten 14 Tagen ist nichts geplant und nichts läuft ab.</li>
            @endforelse
        </ul>
    </section>
</div>

<div class="cms-grid cms-grid--3">
    <section class="cms-panel" aria-labelledby="events-heading">
        <header class="cms-panel__header"><h2 id="events-heading">Nächste Termine</h2>@can('viewAny', \App\Models\Event::class)<a href="{{ route('admin.event.index') }}">Alle</a>@endcan</header>
        <ul class="cms-rows">
            @forelse ($events as $event)
                <li class="cms-row cms-row--date">
                    <span class="cms-date">{{ \App\Support\SiteTime::format($event->starts_at, 'd.m.') }}</span>
                    <div class="cms-row__body">
                        <a class="cms-row__title" href="{{ route('admin.event.edit', $event) }}">{{ $event->displayTitle() }}</a>
                        <p class="cms-row__meta">{{ $event->location?->name ?? $event->venue }}@if (! $event->all_day) · {{ \App\Support\SiteTime::format($event->starts_at, 'H:i') }} Uhr @endif @if ($event->operational_status === \App\Enums\EventOperationalStatus::Cancelled) · <strong>Abgesagt</strong>@endif</p>
                    </div>
                </li>
            @empty
                <li class="cms-empty">Keine anstehenden Termine.</li>
            @endforelse
        </ul>
    </section>

    <section class="cms-panel" aria-labelledby="qualitaet-heading" id="qualitaet">
        <header class="cms-panel__header"><h2 id="qualitaet-heading">Qualität</h2></header>
        <ul class="cms-rows">
            @forelse ($quality as $row)
                <li class="cms-row cms-row--quality">
                    @include('admin.partials.severity', ['severity' => $row['severity']])
                    <div class="cms-row__body">
                        <a class="cms-row__title" href="{{ route('admin.'.$row['resource']->key().'.edit', $row['record']->getKey()) }}">{{ $row['record']->displayTitle() }}</a>
                        <p class="cms-row__meta">{{ $row['message'] }}</p>
                    </div>
                </li>
            @empty
                <li class="cms-empty">Die automatischen Prüfungen haben keine Hinweise ergeben.</li>
            @endforelse
        </ul>
        <p class="cms-panel__note">Automatische Hinweise ersetzen keine manuelle Prüfung.</p>
    </section>

    <section class="cms-panel" aria-labelledby="recent-heading">
        <header class="cms-panel__header"><h2 id="recent-heading">Zuletzt bearbeitet</h2><a href="{{ route('admin.overview') }}">Alle Inhalte</a></header>
        <ul class="cms-rows">
            @forelse ($recent as $row)
                <li class="cms-row">
                    <div class="cms-row__body">
                        <a class="cms-row__title" href="{{ $edit($row) }}">{{ $row['record']->displayTitle() }}</a>
                        <p class="cms-row__meta">{{ $row['resource']->label() }} · {{ $row['record']->updated_at?->locale('de')->diffForHumans() }}</p>
                    </div>
                    @include('admin.partials.state-badge', ['model' => $row['record']])
                </li>
            @empty
                <li class="cms-empty">Noch keine Inhalte.</li>
            @endforelse
        </ul>
    </section>
</div>

@if ($mine->isNotEmpty())
    <section class="cms-panel" aria-labelledby="mine-heading">
        <header class="cms-panel__header"><h2 id="mine-heading">Meine Änderungsvorschläge</h2></header>
        <ul class="cms-rows">
            @foreach ($mine as $proposal)
                <li class="cms-row">
                    <span class="state state--proposal-{{ $proposal->status->value }}">{{ $proposal->status->label() }}</span>
                    <div class="cms-row__body"><a class="cms-row__title" href="{{ route('admin.proposals.show', $proposal) }}">{{ $proposal->proposable?->displayTitle() ?? 'Inhalt' }}</a>@if ($proposal->summary)<p class="cms-row__meta">{{ $proposal->summary }}</p>@endif</div>
                </li>
            @endforeach
        </ul>
    </section>
@endif
@endsection
