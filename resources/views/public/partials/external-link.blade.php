<a class="external-link" href="{{ $resource->url }}">
    <span class="external-link__text">
        <span class="external-link__title">{{ $resource->title }}<span class="visually-hidden"> (externer Link)</span></span>
        @if ($resource->provider_name || $resource->description)<span class="external-link__meta">{{ $resource->description }}@if ($resource->provider_name && $resource->description) · @endif{{ $resource->provider_name }}</span>@endif
    </span>
    <x-icon name="external" class="external-link__icon" />
</a>
@if ($resource->privacy_note)<p class="external-link__privacy">{{ $resource->privacy_note }}</p>@endif
