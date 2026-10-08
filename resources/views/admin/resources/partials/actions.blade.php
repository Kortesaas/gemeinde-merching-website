<section aria-labelledby="actions-heading">
    <h2 id="actions-heading">Weitere Aktionen</h2>
    <ul class="nav-list">
        @can('delete', $model)
            <li>
                <form method="POST" action="{{ route('admin.'.$resource->key().'.destroy', $model->getKey()) }}">
                    @csrf @method('DELETE')
                    <button type="submit" class="button button--secondary">{{ $resource->usesRecycleBin() ? 'In den Papierkorb legen' : 'Löschen' }}</button>
                </form>
            </li>
        @endcan
        @can('restore', $model)
            <li>
                <form method="POST" action="{{ route('admin.'.$resource->key().'.restore', $model->getKey()) }}">
                    @csrf
                    <button type="submit" class="button">Wiederherstellen</button>
                </form>
            </li>
        @endcan
        @can('forceDelete', $model)
            <li>
                <form method="POST" action="{{ route('admin.'.$resource->key().'.force-delete', $model->getKey()) }}">
                    @csrf @method('DELETE')
                    <button type="submit" class="button button--secondary">Endgültig löschen</button>
                </form>
            </li>
        @endcan
    </ul>
</section>
