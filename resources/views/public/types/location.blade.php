@php
    $departments = $model->departments()->where('is_active', true)->get();
    $events = $model->events()->with('canonicalRoute')->visible()->where('starts_at', '>=', now()->subDay())->orderBy('starts_at')->limit(4)->get()->filter(fn ($e) => $e->publicPath() !== null && $e->endsAtForArchiving()->isFuture());
@endphp
@include('public.partials.page-header', ['eyebrow' => $model->type?->label() ?? 'Ort', 'lead' => $model->description])
<div class="content-layout">
    <div class="content-main">
        @if ($model->opening_hours)
            <section class="content-section" aria-labelledby="hours"><h2 id="hours">Öffnungszeiten</h2>
                <ul class="hours-list">@foreach (\App\Support\Content\PublicFormat::lines($model->opening_hours) as $line)<li>{{ $line }}</li>@endforeach</ul>
            </section>
        @endif
        @if ($model->accessibility_note)
            <section class="content-section" aria-labelledby="access"><h2 id="access">Barrierefreiheit vor Ort</h2><p>{{ $model->accessibility_note }}</p></section>
        @endif
        @if ($departments->isNotEmpty())
            <section class="content-section" aria-labelledby="departments"><h2 id="departments">Hier finden Sie</h2>
                <div class="contact-grid">@foreach ($departments as $department)@include('public.partials.contact-card', ['contact' => $department, 'headingTag' => 'h3', 'showHours' => false])@endforeach</div>
            </section>
        @endif
        @if ($events->isNotEmpty())
            <section class="content-section" aria-labelledby="events"><h2 id="events">Nächste Veranstaltungen</h2>
                <ul class="event-list">@foreach ($events as $event)<li>@include('public.partials.event-item', ['record' => $event, 'headingTag' => 'h3'])</li>@endforeach</ul>
            </section>
        @endif
    </div>
    <aside class="content-aside" aria-labelledby="loc-address">
        <h2 id="loc-address" class="aside-heading">Adresse</h2>
        @include('public.partials.location', ['location' => $model, 'showName' => false, 'showHours' => false, 'link' => false])
        @include('public.partials.contact-data', ['contact' => $model])
    </aside>
</div>
