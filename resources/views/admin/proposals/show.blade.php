@extends('layouts.admin')

@php
    $canEdit = auth()->user()->can('update', $proposal);
    $canReview = auth()->user()->can('review', $proposal);
    $canApply = auth()->user()->can('apply', $proposal);
    $recordUrl = route('admin.'.$resource->key().'.edit', $record->getKey());
@endphp

@section('title', $proposal->displayTitle().' – '.$record->displayTitle())

@section('content')
    <p><a href="{{ route('admin.proposals.index') }}">Zu den Freigaben</a> · <a href="{{ $recordUrl }}">Zum veröffentlichten Inhalt</a></p>
    <h1>{{ $proposal->displayTitle() }}: {{ $record->displayTitle() }}</h1>

    <x-status />
    <x-form.error-summary />

    <dl class="summary-list summary-list--inline">
        <dt>Status</dt><dd>{{ $proposal->status->label() }}</dd>
        <dt>{{ $resource->label() }}</dt><dd><a href="{{ $recordUrl }}">{{ $record->displayTitle() }}</a> ({{ $record->publicationState()->label() }})</dd>
        <dt>Vorgeschlagen von</dt><dd>{{ $proposal->author?->name ?? 'unbekannt' }}, {{ \App\Support\SiteTime::format($proposal->created_at) }}</dd>
        @if ($proposal->submitted_at)
            <dt>Eingereicht</dt><dd>{{ \App\Support\SiteTime::format($proposal->submitted_at) }}</dd>
        @endif
        @if ($proposal->reviewed_at)
            <dt>Entschieden</dt><dd>{{ \App\Support\SiteTime::format($proposal->reviewed_at) }} durch {{ $proposal->reviewer?->name ?? 'unbekannt' }}</dd>
        @endif
        @if ($proposal->summary)
            <dt>Beschreibung</dt><dd>{{ $proposal->summary }}</dd>
        @endif
        @if ($proposal->review_comment)
            <dt>Begründung der Ablehnung</dt><dd class="preserve-lines">{{ $proposal->review_comment }}</dd>
        @endif
    </dl>

    <div class="notice" role="note">
        <p>Der veröffentlichte Inhalt bleibt unverändert öffentlich, bis dieser Vorschlag von einer anderen berechtigten Person freigegeben wird.</p>
    </div>

    <section aria-labelledby="diff-heading">
        <h2 id="diff-heading">Änderungen gegenüber dem Ausgangsstand</h2>
        @if ($diff === [])
            <p>Noch keine Änderungen.</p>
        @else
            <div class="table-wrapper">
                <table class="data-table">
                    <caption class="visually-hidden">Geänderte Felder</caption>
                    <thead><tr><th scope="col">Feld</th><th scope="col">Bisher</th><th scope="col">Vorschlag</th>@if ($conflicts !== [])<th scope="col">Inzwischen veröffentlicht</th>@endif</tr></thead>
                    <tbody>
                        @foreach ($diff as $row)
                            <tr>
                                <th scope="row">{{ $row['label'] }}</th>
                                <td class="preserve-lines">{{ $row['before'] }}</td>
                                <td class="preserve-lines">{{ $row['after'] }}</td>
                                @if ($conflicts !== [])
                                    <td class="preserve-lines">{{ $row['live'] === null ? '–' : 'Konflikt: '.$row['live'] }}</td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    @if ($canReview)
        <section aria-labelledby="review-heading">
            <h2 id="review-heading">Prüfung</h2>
            @if ($conflicts !== [])
                <div class="notice notice--warning" role="status">
                    <p>Achtung: {{ count($conflicts) }} Feld(er) wurden seit dem Vorschlag auch im veröffentlichten Inhalt geändert. Bei Freigabe gilt der Vorschlag.</p>
                </div>
            @endif
            @if ($canApply)
                <form method="POST" action="{{ route('admin.proposals.apply', $proposal) }}">
                    @csrf
                    @if ($conflicts !== [])
                        <x-form.checkbox name="confirm_conflicts" label="Ich habe die Konflikte geprüft; der Vorschlag soll die neueren Änderungen ersetzen." />
                    @endif
                    <button type="submit" class="button">Freigeben und veröffentlichen</button>
                </form>
            @else
                <p>Der zugehörige Inhalt liegt im Papierkorb; der Vorschlag kann nur abgelehnt werden.</p>
            @endif
            <form method="POST" action="{{ route('admin.proposals.reject', $proposal) }}">
                @csrf
                <x-form.textarea name="review_comment" label="Begründung (Pflichtfeld bei Ablehnung)" :value="old('review_comment')" rows="3" />
                <button type="submit" class="button button--secondary">Ablehnen</button>
            </form>
        </section>
    @endif

    @if ($canEdit)
        <section aria-labelledby="edit-heading">
            <h2 id="edit-heading">Vorschlag bearbeiten</h2>
            <form method="POST" action="{{ route('admin.proposals.update', $proposal) }}" novalidate>
                @csrf @method('PUT')
                <input type="hidden" name="_form_started" value="1">
                @foreach ($fields as $field)
                    @include($field->view(), [
                        'field' => $field,
                        'model' => $record,
                        'value' => old($field->name, $field->snapshotValue($preview, $proposal->payload)),
                        'orderOverride' => $field instanceof \App\Admin\Fields\BelongsToMany ? $field->snapshotOrders($proposal->payload) : null,
                        'disabled' => false,
                    ])
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
                <form method="POST" action="{{ route('admin.proposals.placements.store', $proposal) }}" class="filter-form">
                    @csrf
                    <input type="hidden" name="kind" value="{{ $kind }}">
                    <x-form.select name="item_id" id="{{ $kind }}-item" label="{{ $kind === 'documents' ? 'Dokument' : 'Link' }} hinzufügen" :options="$config['options']" placeholder="– auswählen –" />
                    <x-form.select name="slot" id="{{ $kind }}-slot" label="Bereich" :options="$config['slots']" />
                    <x-form.field name="group_label" id="{{ $kind }}-group" label="Gruppe (optional)" maxlength="120" autocomplete="off" />
                    <x-form.field name="sort_order" id="{{ $kind }}-sort" label="Reihenfolge" type="number" min="0" max="65535" value="0" />
                    <button type="submit" class="button button--secondary">Zuordnen</button>
                </form>
            @endforeach
        </section>
    @endif

    @can('withdraw', $proposal)
        <form method="POST" action="{{ route('admin.proposals.withdraw', $proposal) }}">
            @csrf
            <button type="submit" class="button button--secondary">Vorschlag zurückziehen</button>
        </form>
    @endcan
@endsection
