@extends('layouts.public')
@section('title', 'Startseite')
@section('content')
    @foreach (\App\Models\SiteAlert::query()->visible()->orderByDesc('publish_at')->get() as $alert)
        <aside class="alert-band" aria-label="Aktueller Hinweis"><strong>{{ $alert->title }}</strong><p>{{ $alert->body }}</p>@if ($alert->link_url)<a href="{{ $alert->link_url }}">{{ $alert->link_label ?: 'Weitere Informationen' }}</a>@endif</aside>
    @endforeach
    <section class="hero" aria-labelledby="home-heading">
        <p class="eyebrow">Willkommen in {{ $siteTitle }}</p>
        <h1 id="home-heading">Wie können wir Ihnen helfen?</h1>
        @include('public.partials.search-form', ['searchId' => 'home-search', 'hero' => true])
        <a href="{{ app(\App\Services\Content\PublicCatalog::class)->sectionPath('services') }}">Bürgerservice entdecken →</a>
    </section>
    @if (config('public.homepage.services') && $shortcuts)
        <section class="section" aria-labelledby="tasks-heading"><div class="section-heading"><h2 id="tasks-heading">Direkt zum Anliegen</h2><a href="{{ app(\App\Services\Content\PublicCatalog::class)->sectionPath('az') }}">Alle Leistungen A–Z</a></div>
            <ul class="link-rows task-grid">@foreach ($shortcuts as $node)<li><a href="{{ $node->item->href() }}">{{ $node->item->label }}</a></li>@endforeach</ul>
        </section>
    @endif
    <div class="columns">
    @if (config('public.homepage.news') && $news->isNotEmpty())
        <section class="section" aria-labelledby="news-heading"><div class="section-heading"><h2 id="news-heading">Aktuelles</h2><a href="{{ app(\App\Services\Content\PublicCatalog::class)->sectionPath('articles') }}">Alle Meldungen</a></div><ul class="link-rows">@foreach ($news as $record)<li>@include('public.partials.card', ['headingTag' => 'h3'])</li>@endforeach</ul></section>
    @endif
    @if (config('public.homepage.events') && $events->isNotEmpty())
        <section class="section" aria-labelledby="events-heading"><div class="section-heading"><h2 id="events-heading">Veranstaltungen</h2><a href="{{ app(\App\Services\Content\PublicCatalog::class)->sectionPath('events') }}">Alle Veranstaltungen</a></div><ul class="link-rows">@foreach ($events as $record)<li>@include('public.partials.card', ['headingTag' => 'h3'])</li>@endforeach</ul></section>
    @endif
    </div>
    @if (config('public.homepage.online') && $online->isNotEmpty())
        <section class="section panel" aria-labelledby="online-heading"><h2 id="online-heading">Digitales Rathaus</h2><ul class="link-rows">@foreach ($online as $link)<li><a href="{{ $link->url }}">{{ $link->title }} ↗</a><p class="meta">Externer Dienst @if ($link->privacy_note) · {{ $link->privacy_note }}@endif</p></li>@endforeach</ul></section>
    @endif
    @if (config('public.homepage.contact') && ($townHall?->isPubliclyReachable() || $central?->isPubliclyReachable()))
        <section class="section" aria-labelledby="town-hall-heading"><h2 id="town-hall-heading">Rathaus und Kontakt</h2><div class="columns">
            @if ($townHall?->isPubliclyReachable()) @include('public.partials.location', ['location' => $townHall]) @endif
            @if ($central?->isPubliclyReachable())<div><h3>{{ $central->name }}</h3>@include('public.partials.contact-data', ['contact' => $central])<a href="{{ route('public.contact') }}">Nachricht schreiben</a></div>@endif
        </div></section>
    @endif
@endsection
