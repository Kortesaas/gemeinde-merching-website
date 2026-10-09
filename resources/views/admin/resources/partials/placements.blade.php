{{-- Documents and managed links placed on this record (slot › group › order). --}}
@php
    $kinds = [];
    if (method_exists($model, 'documentSlots')) {
        $kinds['documents'] = ['label' => 'Dokumente', 'slots' => $model::documentSlots(), 'items' => $model->documents()->withTrashed()->get(), 'options' => \App\Admin\Options::documents()];
    }
    if (method_exists($model, 'resourceSlots')) {
        $kinds['links'] = ['label' => 'Links & Online-Dienste', 'slots' => $model::resourceSlots(), 'items' => $model->externalResources()->withTrashed()->get(), 'options' => \App\Admin\Options::externalResources()];
    }
    $canEdit = $editable && ! $trashed;
@endphp
<section class="editor-card" aria-labelledby="placements-heading">
    <h2 class="editor-card__title" id="placements-heading">Downloads &amp; Links</h2>
    <div class="editor-card__body">
    <p class="form-hint">Dokumente und Links werden einmal zentral gepflegt und hier nur zugeordnet. Entfernen löscht keine Datei.</p>

    @foreach ($kinds as $kind => $config)
        <h3 class="editor-subheading" id="{{ $kind === 'documents' ? 'documents' : 'externalResources' }}">{{ $config['label'] }}</h3>
        @if ($config['items']->isEmpty())
            <p class="meta">Noch nichts zugeordnet.</p>
        @else
            <div class="table-wrapper" role="region" aria-label="Zugeordnete {{ $config['label'] }}, Tabelle" tabindex="0">
                <table class="data-table data-table--stack" role="table">
                    <caption class="visually-hidden">{{ $config['label'] }}</caption>
                    <thead><tr role="row"><th scope="col" role="columnheader">Eintrag</th><th scope="col" role="columnheader">Bereich</th><th scope="col" role="columnheader">Gruppe</th><th scope="col" role="columnheader">Reihenfolge</th>@if ($canEdit)<th scope="col" role="columnheader">Aktionen</th>@endif</tr></thead>
                    <tbody role="rowgroup">
                        @foreach ($config['items'] as $item)
                            @php $pivot = $item->pivot; $formId = 'placement-'.$kind.'-'.$pivot->id; @endphp
                            <tr role="row">
                                <td role="cell" data-label="Eintrag">{{ $item->displayTitle() }}@if ($item->trashed()) (im Papierkorb)@endif</td>
                                <td role="cell" data-label="Bereich">{{ $config['slots'][$pivot->slot] ?? $pivot->slot }}</td>
                                @if ($canEdit)
                                    <td role="cell" data-label="Gruppe">
                                        <label class="visually-hidden" for="{{ $formId }}-group">Gruppe für {{ $item->displayTitle() }}</label>
                                        <input class="form-input" form="{{ $formId }}" id="{{ $formId }}-group" name="group_label" value="{{ $pivot->group_label }}" maxlength="120">
                                    </td>
                                    <td role="cell" data-label="Reihenfolge">
                                        <label class="visually-hidden" for="{{ $formId }}-sort">Reihenfolge für {{ $item->displayTitle() }}</label>
                                        <input class="form-input form-input--small" form="{{ $formId }}" id="{{ $formId }}-sort" type="number" min="0" max="65535" name="sort_order" value="{{ $pivot->sort_order }}">
                                    </td>
                                    <td role="cell" data-label="Aktionen">
                                        <form id="{{ $formId }}" method="POST" action="{{ route('admin.'.$resource->key().'.placements.update', [$model->getKey(), $kind, $pivot->id]) }}" class="inline-form">
                                            @csrf @method('PATCH')
                                            <button type="submit" class="button button--secondary">Speichern<span class="visually-hidden">: {{ $item->displayTitle() }}</span></button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.'.$resource->key().'.placements.destroy', [$model->getKey(), $kind, $pivot->id]) }}" class="inline-form">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="button button--secondary">Entfernen<span class="visually-hidden">: {{ $item->displayTitle() }}</span></button>
                                        </form>
                                    </td>
                                @else
                                    <td role="cell" data-label="Gruppe">{{ $pivot->group_label }}</td>
                                    <td role="cell" data-label="Reihenfolge">{{ $pivot->sort_order }}</td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if ($canEdit)
            <form method="POST" action="{{ route('admin.'.$resource->key().'.placements.store', $model->getKey()) }}" class="placement-add">
                @csrf
                <input type="hidden" name="kind" value="{{ $kind }}">
                <x-form.select name="item_id" id="{{ $kind }}-item" label="{{ $kind === 'documents' ? 'Dokument' : 'Link' }} hinzufügen" :options="$config['options']" placeholder="– auswählen –" />
                <x-form.select name="slot" id="{{ $kind }}-slot" label="Bereich" :options="$config['slots']" />
                <x-form.field name="group_label" id="{{ $kind }}-group" label="Gruppe (optional)" hint="z. B. 2026" maxlength="120" autocomplete="off" />
                <x-form.field name="sort_order" id="{{ $kind }}-sort" label="Reihenfolge" type="number" min="0" max="65535" value="0" />
                <button type="submit" class="button button--secondary"><x-icon name="plus" /> Zuordnen</button>
            </form>
        @endif
    @endforeach
    </div>
</section>
