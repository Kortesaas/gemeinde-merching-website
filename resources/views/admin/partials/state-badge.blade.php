{{-- Status as a labelled badge: text carries the meaning, colour only supports it. --}}
@php
    if (method_exists($model, 'trashed') && $model->trashed()) { [$key, $label] = ['trashed', 'Im Papierkorb']; }
    elseif (method_exists($model, 'publicationState')) { $state = $model->publicationState(); [$key, $label] = [$state->value, $state->label()]; }
    elseif (array_key_exists('is_active', $model->getAttributes())) { [$key, $label] = $model->is_active ? ['active', 'Aktiv'] : ['inactive', 'Inaktiv']; }
    else { [$key, $label] = [null, null]; }
@endphp
@if ($label)<span class="state state--{{ $key }}">{{ $label }}</span>@else<span class="meta">–</span>@endif
