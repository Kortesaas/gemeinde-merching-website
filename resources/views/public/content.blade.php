@extends('layouts.public')
@php
    $attributes = $model->getAttributes();
    $archived = (method_exists($model, 'isInPublicArchive') && $model->isInPublicArchive()) || ($model instanceof \App\Models\Event && $model->endsAtForArchiving()->lessThanOrEqualTo(now()));
    $documents = method_exists($model, 'documents') ? $model->documents()->with(['canonicalRoute', 'replacedBy.canonicalRoute', 'accessibleAlternative.canonicalRoute'])->get()->filter(fn ($d) => $d->isPubliclyReachable()) : collect();
    $links = method_exists($model, 'externalResources') ? $model->externalResources()->visible()->get() : collect();
    $departments = $model instanceof \App\Models\Service ? $model->departments()->with('location.mapResource')->where('is_active', true)->get() : collect();
    if (method_exists($model, 'department') && $model->department?->isPubliclyReachable()) { $departments->push($model->department); }
    $contacts = method_exists($model, 'contacts') ? $model->contacts()->where('is_active', true)->get() : collect();
    if ($model instanceof \App\Models\Event && $model->contactPerson?->isPubliclyReachable()) { $contacts->push($model->contactPerson); }
    $isService = $model instanceof \App\Models\Service;
    $hasAside = $departments->isNotEmpty() || $contacts->isNotEmpty() || ($isService && $model->online_service_mode !== \App\Enums\OnlineServiceMode::NotSpecified);
@endphp
@section('title', $model->displayTitle())
@section('canonical', \App\Support\Routing\PublicPath::absoluteUrl((string) $model->publicPath()))
@section('content')
<article>
    <p class="eyebrow">{{ \App\Http\Controllers\Public\SearchController::LABELS[$model->getMorphClass()] ?? 'Gemeindeinformation' }}
        @if (method_exists($model, 'category') && $model->category) · {{ $model->category->name }} @endif
        @if ($model instanceof \App\Models\Article || $model instanceof \App\Models\PublicNotice) · {{ \App\Support\SiteTime::format($model->publish_at, 'd.m.Y') }} @endif
    </p>
    <h1>{{ $model->displayTitle() }}</h1>
    @if (!empty($attributes['summary']))<p class="lead">{{ $attributes['summary'] }}</p>@endif
    @if ($archived)<p class="notice">Archiv: Dieser Inhalt ist nicht mehr aktuell.</p>@endif
    @if ($model instanceof \App\Models\Event)
        <p class="lead">{{ \App\Support\SiteTime::format($model->starts_at, $model->all_day ? 'd.m.Y' : 'd.m.Y, H:i') }} {{ $model->all_day ? 'ganztägig' : 'Uhr' }}
            @if ($model->ends_at) bis {{ \App\Support\SiteTime::format($model->ends_at, $model->all_day ? 'd.m.Y' : 'd.m.Y, H:i') }} {{ $model->all_day ? '' : 'Uhr' }} @endif
        </p>
        @if ($model->operational_status === \App\Enums\EventOperationalStatus::Cancelled)<p class="alert-band"><strong>Abgesagt</strong> – Diese Veranstaltung findet nicht statt.</p>@endif
        @if ($model->schedule_notice)<p class="alert-band">{{ $model->schedule_notice }}</p>@endif
    @endif
    <div class="content-layout {{ $hasAside ? '' : 'content-layout--single' }}">
        <div class="prose">
            {!! \App\Support\Content\SafeMarkdown::toHtml($attributes['body'] ?? $attributes['description'] ?? null) !!}
            @if (method_exists($model, 'blocks')) @include('public.partials.blocks') @endif
            @if ($model instanceof \App\Models\Gallery) @include('public.partials.gallery', ['gallery'=>$model]) @endif
            @if ($isService) @include('public.partials.service-details') @endif
            @if ($model instanceof \App\Models\CouncilTerm) @include('public.partials.council') @endif
            @if ($model instanceof \App\Models\Location) @include('public.partials.location', ['location'=>$model]) @endif
            @if ($model instanceof \App\Models\Department)
                @include('public.partials.contact-data', ['contact'=>$model])
                @if ($model->opening_hours)<p>{{ $model->opening_hours }}</p>@endif
                @if ($model->location) @include('public.partials.location', ['location'=>$model->location]) @endif
                @foreach ($model->people()->where('is_active',true)->get() as $person)<section class="contact-card"><h2>{{ $person->displayTitle() }}</h2><p>{{ $person->job_title }}</p>@include('public.partials.contact-data', ['contact'=>$person])</section>@endforeach
            @endif
            @if ($model instanceof \App\Models\Organization)
                @include('public.partials.contact-data', ['contact'=>$model])
                @if ($model->street)<p>{{ $model->street }}, {{ $model->postal_code }} {{ $model->city }}</p>@endif
                @if ($model->website)<p><a href="{{ $model->website }}">Website der Organisation ↗</a></p>@endif
                @foreach ($model->links()->get() as $link)<p><a href="{{ $link->url }}">{{ $link->label }}</a></p>@endforeach
            @endif
            @if ($model instanceof \App\Models\Event)
                @if ($model->location) @include('public.partials.location', ['location'=>$model->location]) @elseif ($model->venue)<p>Ort: {{ $model->venue }}</p>@endif
                @if ($model->organization?->isPubliclyReachable())<p>Veranstalter: {{ $model->organization->name }}</p>@elseif ($model->organizer_name)<p>Veranstalter: {{ $model->organizer_name }}</p>@endif
                @if ($model->remarks)<p>{{ $model->remarks }}</p>@endif
                @if ($model->registration_url)<p><a class="button" href="{{ $model->registration_url }}">Zur Anmeldung ↗</a></p>@endif
                @if ($model->url)<p><a href="{{ $model->url }}">Weitere Veranstaltungsinformationen ↗</a></p>@endif
            @endif
            @if (method_exists($model,'media'))
                @foreach ($model->media()->get() as $medium)
                    @if ($medium->isPubliclyReachable() && $medium->isImage() && $medium->hasAccessibleAlternative())
                        <figure>@include('public.partials.image', ['medium'=>$medium]) @if ($medium->caption || $medium->copyright)<figcaption>{{ $medium->caption }} @if ($medium->copyright) – {{ $medium->copyright }} @endif</figcaption>@endif</figure>
                    @endif
                @endforeach
            @endif
            @if ($documents->isNotEmpty())
                <section class="section"><h2>Dokumente und Formulare</h2><ul class="link-rows">@foreach ($documents as $record)<li>@include('public.partials.card', ['headingTag'=>'h3'])</li>@endforeach</ul></section>
            @endif
            @if ($links->isNotEmpty())<section class="section"><h2>Weiterführende Links</h2><ul class="link-rows">@foreach($links as $link)<li><a href="{{ $link->url }}">{{ $link->title }} ↗</a>@if($link->provider_name)<p class="meta">{{ $link->provider_name }}</p>@endif @if($link->privacy_note)<p>{{ $link->privacy_note }}</p>@endif</li>@endforeach</ul></section>@endif
            @if (method_exists($model, 'relatedServices'))
                @php $related=$model->relatedServices()->with('canonicalRoute')->visible()->get()->filter(fn($s)=>$s->publicPath()); @endphp
                @if ($related->isNotEmpty())<section class="section"><h2>Auch interessant</h2><ul class="link-rows">@foreach ($related as $record)<li>@include('public.partials.card', ['headingTag'=>'h3'])</li>@endforeach</ul></section>@endif
            @endif
        </div>
        @if ($hasAside)<aside class="content-aside" aria-label="Zuständigkeit und ergänzende Informationen">
            @if ($departments->isNotEmpty() || $contacts->isNotEmpty())
                <section><h2>Zuständig</h2>@foreach ($departments as $department)<div class="contact-card"><p><strong>{{ $department->name }}</strong></p>@include('public.partials.contact-data', ['contact'=>$department])</div>@endforeach
                @foreach ($contacts as $person)<div class="contact-card"><p><strong>{{ $person->displayTitle() }}</strong></p>@if($person->job_title)<p>{{ $person->job_title }}</p>@endif @include('public.partials.contact-data', ['contact'=>$person]) @if($person->room)<p>Raum {{ $person->room }}</p>@endif @if($person->availability)<p>{{ $person->availability }}</p>@endif</div>@endforeach</section>
            @endif
            @foreach ($departments as $department)
                @if ($department->location?->isPubliclyReachable())<section><h2>{{ $department->location->displayTitle() }}</h2>@include('public.partials.location', ['location'=>$department->location])</section>@endif
            @endforeach
            @if ($isService && $model->online_service_mode !== \App\Enums\OnlineServiceMode::NotSpecified)<section><h2>Online erledigen</h2><p>{{ $model->online_service_mode->label() }}</p>@if ($model->online_service_mode !== \App\Enums\OnlineServiceMode::Unavailable && $model->onlineService?->isPubliclyReachable())<a class="button" href="{{ $model->onlineService->url }}">{{ $model->onlineService->title }} ↗</a>@if($model->onlineService->privacy_note)<p class="meta">{{ $model->onlineService->privacy_note }}</p>@endif @endif</section>@endif
        </aside>@endif
    </div>
    <p class="content-feedback">Stand: {{ \App\Support\SiteTime::format($model->updated_at, 'd.m.Y') }} · <a href="{{ route('public.contact', ['feedback'=>$model->publicPath()]) }}">Fehler melden</a></p>
</article>
@endsection
