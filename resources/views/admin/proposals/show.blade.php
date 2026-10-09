@extends('layouts.admin')

@php
    $canEdit = auth()->user()->can('update', $proposal);
    $canReview = auth()->user()->can('review', $proposal);
    $canApply = auth()->user()->can('apply', $proposal);
    $recordUrl = route('admin.'.$resource->key().'.edit', $record->getKey());
@endphp

@section('title', $proposal->displayTitle().' – '.$record->displayTitle())

@section('content')
    <div class="editor-bar">
        <a class="editor-bar__back" href="{{ route('admin.proposals.index') }}"><x-icon name="arrow-left" /> Freigaben</a>
        <span class="state state--proposal-{{ $proposal->status->value }}">{{ $proposal->status->label() }}</span>
        <span class="editor-bar__meta">von {{ $proposal->author?->name ?? 'unbekannt' }} · {{ \App\Support\SiteTime::format($proposal->submitted_at ?? $proposal->created_at) }}</span>
        <div class="editor-bar__actions"><a class="button button--secondary" href="{{ $recordUrl }}">Veröffentlichten Inhalt öffnen</a></div>
    </div>
    <p class="cms-eyebrow">{{ $proposal->displayTitle() }} · {{ $resource->label() }}</p>
    <h1 class="editor-title">{{ $record->displayTitle() }}</h1>

    <x-status />
    <x-form.error-summary />

    <dl class="proposal-facts">
        <div><dt>Veröffentlichter Inhalt</dt><dd>@include('admin.partials.state-badge', ['model' => $record])</dd></div>
        <div><dt>Vorgeschlagen von</dt><dd>{{ $proposal->author?->name ?? 'unbekannt' }}, {{ \App\Support\SiteTime::format($proposal->created_at) }}</dd></div>
        @if ($proposal->submitted_at)<div><dt>Eingereicht</dt><dd>{{ \App\Support\SiteTime::format($proposal->submitted_at) }}</dd></div>@endif
        @if ($proposal->reviewed_at)<div><dt>Entschieden</dt><dd>{{ \App\Support\SiteTime::format($proposal->reviewed_at) }} durch {{ $proposal->reviewer?->name ?? 'unbekannt' }}</dd></div>@endif
        @if ($proposal->summary)<div class="proposal-facts__wide"><dt>Beschreibung der Änderung</dt><dd>„{{ $proposal->summary }}“</dd></div>@endif
        @if ($proposal->review_comment)<div class="proposal-facts__wide"><dt>Begründung der Ablehnung</dt><dd class="preserve-lines">{{ $proposal->review_comment }}</dd></div>@endif
    </dl>

    <p class="notice" role="note">Der veröffentlichte Inhalt bleibt unverändert öffentlich, bis eine andere berechtigte Person diesen Vorschlag freigibt.</p>

    @if ($conflicts !== [])
        <div class="notice notice--warning conflict-notice" role="status">
            <p class="notice__title"><x-icon name="warning" /> {{ count($conflicts) }} {{ count($conflicts) === 1 ? 'Konflikt' : 'Konflikte' }}</p>
            <p>Diese Felder wurden nach dem Vorschlag auch im veröffentlichten Inhalt geändert. Bei Freigabe ersetzt der Vorschlag die neueren Änderungen.</p>
        </div>
    @endif

    <section class="cms-panel" aria-labelledby="diff-heading">
        <header class="cms-panel__header"><h2 id="diff-heading">Vergleich: veröffentlicht und vorgeschlagen</h2><span class="cms-row__meta">{{ count($diff) }} {{ count($diff) === 1 ? 'Änderung' : 'Änderungen' }}</span></header>
        @if ($diff === [])
            <p class="cms-empty">Noch keine Änderungen.</p>
        @else
            <div class="diff-list">
                @foreach ($diff as $row)
                    <section class="diff-item {{ $row['live'] !== null ? 'diff-item--conflict' : '' }}" aria-labelledby="diff-{{ $loop->index }}">
                        <h3 id="diff-{{ $loop->index }}">{{ $row['label'] }}</h3>
                        <div class="diff-columns">
                            <div class="diff-before"><p class="diff-label">Ausgangsstand</p><div class="preserve-lines">{{ $row['before'] !== '' ? $row['before'] : '– leer –' }}</div></div>
                            <div class="diff-after"><p class="diff-label">Vorschlag</p><div class="preserve-lines">{{ $row['after'] !== '' ? $row['after'] : '– leer –' }}</div></div>
                            @if ($row['live'] !== null)
                                <div class="diff-live"><p class="diff-label"><x-icon name="warning" /> Inzwischen veröffentlicht</p><div class="preserve-lines">Konflikt: {{ $row['live'] }}</div></div>
                            @endif
                        </div>
                    </section>
                @endforeach
            </div>
        @endif
    </section>

    @if ($canReview)
        <section class="cms-panel review-panel" aria-labelledby="review-heading">
            <header class="cms-panel__header"><h2 id="review-heading">Entscheidung</h2></header>
            <div class="review-panel__body">
            @if ($canApply)
                <form method="POST" action="{{ route('admin.proposals.apply', $proposal) }}">
                    @csrf
                    @if ($conflicts !== [])
                        <x-form.checkbox name="confirm_conflicts" label="Ich habe die Konflikte geprüft; der Vorschlag soll die neueren Änderungen ersetzen." />
                    @endif
                    <p class="form-hint">Die geänderten Teile werden übernommen und sofort veröffentlicht; es entsteht eine neue Version.</p>
                    <button type="submit" class="button">Freigeben und veröffentlichen</button>
                </form>
            @else
                <p>Der zugehörige Inhalt liegt im Papierkorb; der Vorschlag kann nur abgelehnt werden.</p>
            @endif
            <form method="POST" action="{{ route('admin.proposals.reject', $proposal) }}" class="review-panel__reject">
                @csrf
                <x-form.textarea name="review_comment" label="Begründung (Pflichtfeld bei Ablehnung)" :value="old('review_comment')" rows="3" hint="Die Begründung sieht die Person, die den Vorschlag eingereicht hat." />
                <button type="submit" class="button button--secondary">Zurück an Autor/in (ablehnen)</button>
            </form>
            </div>
        </section>
    @endif

    @if ($canEdit)
        <section class="cms-panel" aria-labelledby="edit-heading">
            <header class="cms-panel__header"><h2 id="edit-heading">Vorschlag bearbeiten</h2></header>
            <div class="cms-panel__body">
            <form method="POST" action="{{ route('admin.proposals.update', $proposal) }}" novalidate>
                @csrf @method('PUT')
                <input type="hidden" name="_form_started" value="1">
                @foreach (\App\Support\Content\EditorSections::group($fields) as $section => $group)
                    <details class="editor-section" open><summary><span>{{ $section }}</span><x-icon name="chevron-down" class="editor-section__chevron" /></summary><div class="field-grid">
                    @foreach ($group as $field)
                    @include($field->view(), [
                        'field' => $field,
                        'model' => $record,
                        'value' => old($field->name, $field->snapshotValue($preview, $proposal->payload)),
                        'orderOverride' => $field instanceof \App\Admin\Fields\BelongsToMany ? $field->snapshotOrders($proposal->payload) : null,
                        'disabled' => false,
                    ])
                @endforeach
                    </div></details>
                @endforeach
                <x-form.field name="proposal_summary" label="Beschreibung der Änderung (für die Prüfung)" :value="old('proposal_summary', $proposal->summary)" maxlength="255" autocomplete="off" />
                <input type="hidden" name="_form_complete" value="1">
                <button type="submit" class="button button--secondary" name="action" value="save">Vorschlag speichern</button>
                @can('submit', $proposal)
                    <button type="submit" class="button" name="action" value="submit">Speichern und zur Prüfung einreichen</button>
                @endcan
            </form>

            @foreach ($kinds as $kind => $config)
                <h3>{{ $config['label'] }} im Vorschlag</h3>
                @if ($config['rows'] === [])
                    <p>Keine Zuordnungen.</p>
                @else
                    <ul>
                        @foreach ($config['rows'] as $index => $row)
                            <li>
                                {{ $config['options'][$row['id']] ?? '#'.$row['id'] }} – {{ $config['slots'][$row['slot']] ?? $row['slot'] }}@if ($row['group_label']) › {{ $row['group_label'] }}@endif, Position {{ $row['sort_order'] }}
                                <form method="POST" action="{{ route('admin.proposals.placements.destroy', [$proposal, $kind, $index]) }}" class="inline-form">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="button button--secondary">Entfernen<span class="visually-hidden">: {{ $config['options'][$row['id']] ?? '#'.$row['id'] }}</span></button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @endif
                <form method="POST" action="{{ route('admin.proposals.placements.store', $proposal) }}" class="placement-add">
                    @csrf
                    <input type="hidden" name="kind" value="{{ $kind }}">
                    <x-form.select name="item_id" id="{{ $kind }}-item" label="{{ $kind === 'documents' ? 'Dokument' : 'Link' }} hinzufügen" :options="$config['options']" placeholder="– auswählen –" />
                    <x-form.select name="slot" id="{{ $kind }}-slot" label="Bereich" :options="$config['slots']" />
                    <x-form.field name="group_label" id="{{ $kind }}-group" label="Gruppe (optional)" maxlength="120" autocomplete="off" />
                    <x-form.field name="sort_order" id="{{ $kind }}-sort" label="Reihenfolge" type="number" min="0" max="65535" value="0" />
                    <button type="submit" class="button button--secondary">Zuordnen</button>
                </form>
            @endforeach
            </div>
        </section>
    @endif

    @can('withdraw', $proposal)
        <form method="POST" action="{{ route('admin.proposals.withdraw', $proposal) }}">
            @csrf
            <button type="submit" class="button button--secondary">Vorschlag zurückziehen</button>
        </form>
    @endcan
@endsection
