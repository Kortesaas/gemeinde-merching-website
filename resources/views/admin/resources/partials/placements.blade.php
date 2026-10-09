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
<section aria-labelledby="placements-heading">
    <h2 id="placements-heading">Zugeordnete Dokumente und Links</h2>
    <p class="form-hint">Dokumente und Links werden einmal gepflegt und hier nur zugeordnet. Entfernen löscht nichts.</p>

    @foreach ($kinds as $kind => $config)
        <h3 id="{{ $kind === 'documents' ? 'documents' : 'externalResources' }}">{{ $config['label'] }}</h3>
        @if ($config['items']->isEmpty())
            <p>Keine Zuordnungen.</p>
        @else
            <div class="table-wrapper" role="region" aria-label="Datentabelle, horizontal verschiebbar" tabindex="0">
                <table class="data-table">
                    <caption class="visually-hidden">{{ $config['label'] }}</caption>
                    <thead><tr><th scope="col">Eintrag</th><th scope="col">Bereich</th><th scope="col">Gruppe</th><th scope="col">Reihenfolge</th>@if ($canEdit)<th scope="col">Aktionen</th>@endif</tr></thead>
                    <tbody>
                        @foreach ($config['items'] as $item)
                            @php $pivot = $item->pivot; $formId = 'placement-'.$kind.'-'.$pivot->id; @endphp
                            <tr>
                                <td>{{ $item->displayTitle() }}@if ($item->trashed()) (im Papierkorb)@endif</td>
                                <td>{{ $config['slots'][$pivot->slot] ?? $pivot->slot }}</td>
                                @if ($canEdit)
                                    <td>
                                        <label class="visually-hidden" for="{{ $formId }}-group">Gruppe für {{ $item->displayTitle() }}</label>
                                        <input class="form-input form-input--small" form="{{ $formId }}" id="{{ $formId }}-group" name="group_label" value="{{ $pivot->group_label }}" maxlength="120">
                                    </td>
                                    <td>
                                        <label class="visually-hidden" for="{{ $formId }}-sort">Reihenfolge für {{ $item->displayTitle() }}</label>
                                        <input class="form-input form-input--small" form="{{ $formId }}" id="{{ $formId }}-sort" type="number" min="0" max="65535" name="sort_order" value="{{ $pivot->sort_order }}">
                                    </td>
                                    <td>
                                        <form id="{{ $formId }}" method="POST" action="{{ route('admin.'.$resource->key().'.placements.update', [$model->getKey(), $kind, $pivot->id]) }}" class="inline-form">
                                            @csrf @method('PATCH')
                                            <button type="submit" class="button button--secondary">Ändern<span class="visually-hidden">: {{ $item->displayTitle() }}</span></button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.'.$resource->key().'.placements.destroy', [$model->getKey(), $kind, $pivot->id]) }}" class="inline-form">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="button button--secondary">Entfernen<span class="visually-hidden">: {{ $item->displayTitle() }}</span></button>
                                        </form>
                                    </td>
                                @else
                                    <td>{{ $pivot->group_label }}</td>
                                    <td>{{ $pivot->sort_order }}</td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if ($canEdit)
            <form method="POST" action="{{ route('admin.'.$resource->key().'.placements.store', $model->getKey()) }}" class="filter-form">
                @csrf
                <input type="hidden" name="kind" value="{{ $kind }}">
                <x-form.select name="item_id" id="{{ $kind }}-item" label="{{ $kind === 'documents' ? 'Dokument' : 'Link' }} hinzufügen" :options="$config['options']" placeholder="– auswählen –" />
                <x-form.select name="slot" id="{{ $kind }}-slot" label="Bereich" :options="$config['slots']" />
                <x-form.field name="group_label" id="{{ $kind }}-group" label="Gruppe (optional)" hint="z. B. 2026" maxlength="120" autocomplete="off" />
                <x-form.field name="sort_order" id="{{ $kind }}-sort" label="Reihenfolge" type="number" min="0" max="65535" value="0" />
                <button type="submit" class="button button--secondary">Zuordnen</button>
            </form>
        @endif
    @endforeach
</section>
