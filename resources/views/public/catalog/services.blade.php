@php
    $azPath = $catalog->sectionPath('az');
    $alphabet = range('A', 'Z');
    $byCategory = $records->getCollection()->groupBy(fn ($s) => $s->category?->name ?? 'Weitere Leistungen')->sortKeys();
@endphp
@include('public.partials.search-form', ['searchId' => 'service-search', 'searchLabel' => 'Im Bürgerservice suchen', 'searchType' => 'service', 'pill' => true])

@unless ($filtered)
<div class="service-entry">
    <section aria-labelledby="az-heading">
        <h2 id="az-heading">Ich weiß, was ich brauche</h2>
        <p class="meta">Alle Leistungen nach Anfangsbuchstabe.</p>
        <nav class="alphabet alphabet--grid" aria-label="Leistungen nach Anfangsbuchstabe">
            <ul>@foreach ($alphabet as $letter)<li>@if (in_array($letter, $letters, true))<a href="{{ $azPath }}#buchstabe-{{ strtolower($letter) }}" aria-label="Leistungen mit {{ $letter }}">{{ $letter }}</a>@else<span aria-hidden="true">{{ $letter }}</span>@endif</li>@endforeach</ul>
        </nav>
        <a class="more-link" href="{{ $azPath }}">Alle Leistungen von A bis Z <x-icon name="arrow-right" /></a>
    </section>
    @if ($situations->isNotEmpty())
        <section aria-labelledby="life-heading">
            <h2 id="life-heading">Ich bin in einer Lebenslage</h2>
            <p class="meta">Was zu tun ist – Schritt für Schritt.</p>
            <ul class="link-list">
                @foreach ($situations as $situation)
                    <li><a class="link-row" href="{{ \App\Support\Routing\PublicPath::toUrl($situation->publicPath()) }}"><span class="link-row__text"><span class="link-row__title">{{ $situation->title }}</span></span><x-icon name="arrow-right" class="link-row__arrow" /></a></li>
                @endforeach
            </ul>
        </section>
    @endif
</div>

@if ($shortcuts)
    <section class="section" aria-labelledby="popular-heading">
        <h2 id="popular-heading">Häufig gesucht</h2>
        <ul class="link-grid">
            @foreach ($shortcuts as $node)
                @php $target = $node->target; $department = $target instanceof \App\Models\Service ? $target->departments()->where('is_active', true)->first() : null; @endphp
                <li><a class="link-row" href="{{ $node->href }}"><span class="link-row__text"><span class="link-row__title">{{ $node->item->label }}@if ($target instanceof \App\Models\Service && $target->onlineService?->isPubliclyReachable() && in_array($target->online_service_mode, [\App\Enums\OnlineServiceMode::Application, \App\Enums\OnlineServiceMode::Appointment], true)) <span class="badge badge--online">Online</span>@endif</span>@if ($department)<span class="link-row__meta">{{ $department->name }}</span>@endif</span><x-icon name="arrow-right" class="link-row__arrow" /></a></li>
            @endforeach
        </ul>
    </section>
@endif

@if ($portal || $onlineServices->isNotEmpty())
    <section class="section panel panel--split" aria-labelledby="online-services-heading">
        <div>
            <h2 id="online-services-heading">Online-Dienste</h2>
            <p>Viele Anträge erledigen Sie unabhängig von den Öffnungszeiten.</p>
            @if ($portal)<a class="button" href="{{ $portal->url }}">{{ $portal->title }}<span class="visually-hidden"> (externer Link)</span> <x-icon name="external" /></a><p class="meta">Externer Dienst · Sie verlassen die Website der Gemeinde.</p>@endif
        </div>
        @if ($onlineServices->isNotEmpty())
            <details class="accordion accordion--panel">
                <summary><span>{{ $onlineServices->count() }} {{ $onlineServices->count() === 1 ? 'Antrag' : 'Anträge' }}, die Sie online stellen können</span><x-icon name="plus" class="accordion__plus" /><x-icon name="minus" class="accordion__minus" /></summary>
                <ul class="external-list accordion__body">@foreach ($onlineServices as $resource)<li>@include('public.partials.external-link', ['resource' => $resource])</li>@endforeach</ul>
            </details>
        @endif
    </section>
@endif

@if ($documentCategories->isNotEmpty() || $people->isNotEmpty())
    <div class="home-columns section">
        @if ($documentCategories->isNotEmpty())
            <section aria-labelledby="forms-heading">
                <div class="section-heading"><h2 id="forms-heading">Formulare und Dokumente</h2><a class="more-link" href="{{ $catalog->sectionPath('documents') }}">Alle Downloads <x-icon name="arrow-right" /></a></div>
                <ul class="link-list">
                    @foreach ($documentCategories as $name => $count)
                        @php $categoryId = \App\Models\Category::query()->where('context', 'document')->where('name', $name)->value('id'); @endphp
                        <li><a class="link-row" href="{{ $catalog->sectionPath('documents') }}{{ $categoryId ? '?category='.$categoryId : '' }}"><span class="link-row__text"><span class="link-row__title">{{ $name }}</span></span><span class="link-row__count">{{ $count }}</span></a></li>
                    @endforeach
                </ul>
            </section>
        @endif
        @if ($people->isNotEmpty())
            <section aria-labelledby="people-heading">
                <div class="section-heading"><h2 id="people-heading">Ansprechpersonen</h2><a class="more-link" href="{{ $azPath }}">Wer ist zuständig? <x-icon name="arrow-right" /></a></div>
                <ul class="person-list">
                    @foreach ($people as $person)
                        <li>
                            <span class="person-list__name">{{ $person->displayTitle() }}</span>
                            <span class="person-list__meta">{{ $person->departments->first()?->name }}@if ($person->job_title && $person->departments->isNotEmpty()) · @endif{{ $person->job_title }}</span>
                            @if ($person->phone)<a class="person-list__phone" href="{{ \App\Support\Content\PublicFormat::phoneHref($person->phone) }}"><span class="visually-hidden">Telefon {{ $person->displayTitle() }}: </span>{{ $person->phone }}</a>@endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
@endif
@endunless

<section class="section" aria-labelledby="all-services-heading">
    <h2 id="all-services-heading">{{ $filtered ? 'Gefundene Leistungen' : 'Alle Leistungen nach Themen' }}</h2>
    @include('public.catalog.filters', ['filterLabel' => 'Leistungen filtern', 'withSearch' => false])
    @if ($records->isEmpty())
        <div class="empty-state"><h3>Keine passende Leistung gefunden</h3><p>Versuchen Sie einen anderen Begriff oder sehen Sie in der Übersicht von A bis Z nach.</p><a href="{{ $azPath }}">Leistungen von A bis Z</a></div>
    @else
        <div class="topic-grid">
            @foreach ($byCategory as $name => $services)
                <section class="topic" aria-labelledby="topic-{{ $loop->index }}">
                    <h3 id="topic-{{ $loop->index }}">{{ $name }}</h3>
                    <ul>@foreach ($services as $service)<li><a href="{{ \App\Support\Routing\PublicPath::toUrl($service->publicPath()) }}">{{ $service->displayTitle() }}</a>@if ($service->onlineService?->isPubliclyReachable() && in_array($service->online_service_mode, [\App\Enums\OnlineServiceMode::Application, \App\Enums\OnlineServiceMode::Appointment], true)) <span class="badge badge--online">Online</span>@endif</li>@endforeach</ul>
                </section>
            @endforeach
        </div>
    @endif
</section>
