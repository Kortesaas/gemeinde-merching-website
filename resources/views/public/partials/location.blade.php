@if ($location->isPubliclyReachable())
    <div>
        <p>{{ $location->displayTitle() }}@if ($location->street), {{ $location->street }}@endif @if ($location->postal_code || $location->city), {{ $location->postal_code }} {{ $location->city }}@endif</p>
        @if ($location->accessibility_note)<p>Zugänglichkeit: {{ $location->accessibility_note }}</p>@endif
        @if ($location->opening_hours)<p>{{ $location->opening_hours }}</p>@endif
        @if ($location->mapResource && $location->mapResource->isPubliclyReachable())
            <p><a href="{{ $location->mapResource->url }}">{{ $location->mapResource->title }}</a>@if ($location->mapResource->privacy_note) – {{ $location->mapResource->privacy_note }}@endif</p>
        @endif
    </div>
@endif
