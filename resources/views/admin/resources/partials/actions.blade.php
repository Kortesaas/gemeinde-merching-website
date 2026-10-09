@php $canDelete = auth()->user()->can('delete', $model); $canRestore = auth()->user()->can('restore', $model); $canForce = auth()->user()->can('forceDelete', $model); @endphp
@if ($canDelete || $canRestore || $canForce)
<section class="editor-card editor-card--danger" aria-labelledby="actions-heading">
    <h2 class="editor-card__title" id="actions-heading">Weitere Aktionen</h2>
    <div class="editor-card__body editor-actions">
        @if ($canRestore)
            <form method="POST" action="{{ route('admin.'.$resource->key().'.restore', $model->getKey()) }}">
                @csrf
                <button type="submit" class="button">Wiederherstellen</button>
            </form>
        @endif
        @if ($canDelete)
            <form method="POST" action="{{ route('admin.'.$resource->key().'.destroy', $model->getKey()) }}" data-confirm="{{ $resource->usesRecycleBin() ? 'Diesen Eintrag in den Papierkorb legen? Er ist dann nicht mehr öffentlich sichtbar.' : 'Diesen Eintrag löschen?' }}">
                @csrf @method('DELETE')
                <button type="submit" class="button button--secondary"><x-icon name="trash" /> {{ $resource->usesRecycleBin() ? 'In den Papierkorb legen' : 'Löschen' }}</button>
            </form>
        @endif
        @if ($canForce)
            <form method="POST" action="{{ route('admin.'.$resource->key().'.force-delete', $model->getKey()) }}" data-confirm="Endgültig löschen? Dies kann nicht rückgängig gemacht werden.">
                @csrf @method('DELETE')
                <button type="submit" class="button button--danger">Endgültig löschen</button>
            </form>
        @endif
        <p class="form-hint">{{ $resource->usesRecycleBin() ? 'Einträge im Papierkorb lassen sich wiederherstellen. Verwendete Einträge können nicht endgültig gelöscht werden.' : 'Das Löschen wird protokolliert.' }}</p>
    </div>
</section>
@endif
