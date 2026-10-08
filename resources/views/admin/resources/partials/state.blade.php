{{-- Status as text (never colour only). --}}
@if (method_exists($model, 'trashed') && $model->trashed())
    Im Papierkorb
@elseif (method_exists($model, 'publicationState'))
    {{ $model->publicationState()->label() }}
@elseif (array_key_exists('is_active', $model->getAttributes()))
    {{ $model->is_active ? 'Aktiv' : 'Inaktiv' }}
@else
    –
@endif
