{{-- Person or department; people never have portraits. --}}
@php $isPerson = $contact instanceof \App\Models\Person; $tag = $headingTag ?? 'p'; if (method_exists($contact, 'canonicalRoute')) { $contact->loadMissing('canonicalRoute'); } @endphp
<div class="contact-card">
    <{{ $tag }} class="contact-card__name">
        @if (! $isPerson && method_exists($contact, 'publicPath') && $contact->publicPath())<a href="{{ \App\Support\Routing\PublicPath::toUrl($contact->publicPath()) }}">{{ $contact->displayTitle() }}</a>@else{{ $contact->displayTitle() }}@endif
    </{{ $tag }}>
    @if ($isPerson && ($contact->job_title || ($role ?? null)))<p class="contact-card__role">{{ $role ?? $contact->job_title }}</p>@endif
    @if ($isPerson && ($showResponsibilities ?? false) && $contact->responsibilities && trim($contact->responsibilities) !== trim($role ?? $contact->job_title ?? ''))<p class="contact-card__detail">{{ implode(' · ', \App\Support\Content\PublicFormat::lines($contact->responsibilities)) }}</p>@endif
    @include('public.partials.contact-data', ['contact' => $contact])
    @if ($isPerson && ($contact->room || $contact->availability))<p class="contact-card__detail">@if ($contact->room)Zimmer {{ $contact->room }}@endif @if ($contact->room && $contact->availability)<br>@endif{{ $contact->availability }}</p>@endif
    @if (! $isPerson && $contact->opening_hours && ($showHours ?? true))<p class="contact-card__detail">@foreach (\App\Support\Content\PublicFormat::lines($contact->opening_hours) as $line){{ $line }}@if (! $loop->last)<br>@endif @endforeach</p>@endif
</div>
