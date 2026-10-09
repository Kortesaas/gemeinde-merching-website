@php $location->loadMissing(['mapResource', 'canonicalRoute']); $tag = $headingTag ?? 'p'; @endphp
@if ($location->isPubliclyReachable())
    <div class="location-card">
        @if ($showName ?? true)<{{ $tag }} class="location-card__name">@if ($location->publicPath() && ($link ?? true))<a href="{{ \App\Support\Routing\PublicPath::toUrl($location->publicPath()) }}">{{ $location->displayTitle() }}</a>@else{{ $location->displayTitle() }}@endif</{{ $tag }}>@endif
        @if ($location->street || $location->city)<p class="location-card__address"><x-icon name="pin" /><span>{{ $location->street }}@if ($location->street)<br>@endif{{ $location->postal_code }} {{ $location->city }}</span></p>@endif
        @if ($location->opening_hours && ($showHours ?? true))<div class="location-card__hours"><x-icon name="clock" /><ul class="plain-list">@foreach (\App\Support\Content\PublicFormat::lines($location->opening_hours) as $line)<li>{{ $line }}</li>@endforeach</ul></div>@endif
        @if ($location->accessibility_note)<p class="location-card__access"><span class="location-card__label">Zugänglichkeit:</span> {{ $location->accessibility_note }}</p>@endif
        @if ($location->mapResource && $location->mapResource->isPubliclyReachable())
            <p class="location-card__map"><a href="{{ $location->mapResource->url }}">{{ $location->mapResource->title }}<span class="visually-hidden"> (externer Link)</span><x-icon name="external" class="icon--inline" /></a>@if ($location->mapResource->privacy_note)<span class="location-card__privacy">{{ $location->mapResource->privacy_note }}</span>@endif</p>
        @endif
    </div>
@endif
