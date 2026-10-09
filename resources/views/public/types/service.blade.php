@php
    $model->loadMissing(['category', 'onlineService', 'fees']);
    $departments = $model->departments()->with('location.mapResource')->where('is_active', true)->get();
    $mode = $model->online_service_mode;
    $online = $model->onlineService?->isPubliclyReachable() ? $model->onlineService : null;
    $actionable = $online && in_array($mode, [\App\Enums\OnlineServiceMode::Application, \App\Enums\OnlineServiceMode::Appointment, \App\Enums\OnlineServiceMode::Information], true);
    $fees = $model->fees;
    $firstFee = $fees->first(fn ($fee) => $fee->amount !== null);
    $primary = $departments->first();
    $place = $primary?->location?->isPubliclyReachable() ? $primary->location : null;
    $requirements = \App\Support\Content\PublicFormat::lines($model->prerequisites);
    $items = \App\Support\Content\PublicFormat::lines($model->required_items);
    $related = $model->relatedServices()->with('canonicalRoute')->visible()->get()->filter(fn ($s) => $s->publicPath() !== null);
    $situations = $model->lifeSituations()->with('canonicalRoute')->visible()->get()->filter(fn ($s) => $s->publicPath() !== null);
    $actionLabel = match ($mode) { \App\Enums\OnlineServiceMode::Appointment => 'Online-Termin vereinbaren', \App\Enums\OnlineServiceMode::Application => 'Online beantragen', default => 'Online-Informationen' };
@endphp
@include('public.partials.page-header', ['eyebrow' => 'Leistung'.($model->category ? ' · '.$model->category->name : ''), 'lead' => $model->summary])
<div class="content-layout">
    <div class="content-main">
        @if ($firstFee || $primary || $model->processing_duration)
            <dl class="fact-strip">
                @if ($firstFee)<div><dt>Gebühr</dt><dd><strong class="fact-strip__value">{{ \App\Support\Content\PublicFormat::money($firstFee->amount) }}</strong><span>{{ $firstFee->description }}</span></dd></div>@endif
                @if ($primary)<div><dt>Wo</dt><dd><strong class="fact-strip__value">{{ $place?->displayTitle() ?? $primary->name }}</strong><span>{{ $place ? $primary->name : 'zuständige Stelle' }}</span></dd></div>@endif
                @if ($model->processing_duration)<div><dt>Dauer</dt><dd><strong class="fact-strip__value fact-strip__value--text">{{ $model->processing_duration }}</strong></dd></div>@endif
            </dl>
        @endif
        @if ($actionable || $primary?->phone)
            <div class="action-row">
                @if ($actionable)<a class="button button--pill" href="{{ $online->url }}">{{ $actionLabel }}<span class="visually-hidden"> (externer Link)</span> <x-icon name="external" /></a>@endif
                @if ($primary?->phone)<a class="{{ $actionable ? 'action-link' : 'button button--pill' }}" href="{{ \App\Support\Content\PublicFormat::phoneHref($primary->phone) }}"><x-icon name="phone" /> {{ $primary->name }} anrufen</a>@endif
                @if ($place?->opening_hours)<a class="action-link" href="#zustaendig">Öffnungszeiten</a>@endif
            </div>
            @if ($actionable)<p class="meta">{{ $mode->label() }} über {{ $online->provider_name ?? 'einen externen Dienst' }} · externer Dienst</p>@endif
        @endif

        @if ($model->body)<div class="prose">{!! \App\Support\Content\SafeMarkdown::toHtml($model->body) !!}</div>@endif

        @if ($requirements)
            <section class="content-section" aria-labelledby="voraussetzungen"><h2 id="voraussetzungen">Voraussetzungen</h2>
                @if (count($requirements) > 1)<ul class="check-list">@foreach ($requirements as $line)<li>{{ $line }}</li>@endforeach</ul>@else<p>{{ $requirements[0] }}</p>@endif
            </section>
        @endif
        @if ($items)
            <section class="content-section" aria-labelledby="unterlagen"><h2 id="unterlagen">Das bringen Sie mit</h2>
                @if (count($items) > 1)<ul class="check-list">@foreach ($items as $line)<li>{{ $line }}</li>@endforeach</ul>@else<p>{{ $items[0] }}</p>@endif
            </section>
        @endif
        @if ($model->important_notice)
            <aside class="callout callout--warning" aria-label="Wichtiger Hinweis"><x-icon name="warning" class="callout__icon" /><div><p class="callout__title">Wichtiger Hinweis</p><p>{{ $model->important_notice }}</p></div></aside>
        @endif
        @if ($fees->isNotEmpty())
            <section class="content-section" aria-labelledby="gebuehren"><h2 id="gebuehren">Gebühren</h2>
                <table class="fee-table">
                    <caption class="visually-hidden">Gebühren</caption>
                    <thead><tr><th scope="col">Leistung</th><th scope="col" class="fee-table__amount">Betrag</th></tr></thead>
                    <tbody>
                        @foreach ($fees as $fee)
                            <tr>
                                <th scope="row">{{ $fee->description }}@if ($fee->context || $fee->note)<span class="fee-table__detail">{{ $fee->context }}@if ($fee->context && $fee->note) · @endif{{ $fee->note }}</span>@endif</th>
                                <td class="fee-table__amount">{{ \App\Support\Content\PublicFormat::money($fee->amount) ?? '–' }}@if ($fee->amount === null)<span class="visually-hidden">kein fester Betrag</span>@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        @endif
        @if ($model->processing_duration && ! ($firstFee || $primary))
            <section class="content-section"><h2>Bearbeitungsdauer</h2><p>{{ $model->processing_duration }}</p></section>
        @endif

        @include('public.partials.blocks')
        @include('public.partials.downloads', ['owner' => $model])
        @include('public.partials.links', ['owner' => $model, 'links' => $links->reject(fn ($l) => $online && $l->is($online))])

        @if ($related->isNotEmpty() || $situations->isNotEmpty())
            <section class="content-section" aria-labelledby="verwandt"><h2 id="verwandt">Auch interessant</h2>
                <ul class="inline-links">
                    @foreach ($related as $service)<li><a href="{{ \App\Support\Routing\PublicPath::toUrl($service->publicPath()) }}">{{ $service->displayTitle() }}</a></li>@endforeach
                    @foreach ($situations as $situation)<li><a href="{{ \App\Support\Routing\PublicPath::toUrl($situation->publicPath()) }}">Lebenslage: {{ $situation->title }}</a></li>@endforeach
                </ul>
            </section>
        @endif
    </div>
    <aside class="content-aside" aria-labelledby="zustaendig">
        <h2 id="zustaendig" class="aside-heading">Zuständig</h2>
        @forelse ($departments as $department)
            @include('public.partials.contact-card', ['contact' => $department, 'showHours' => false])
        @empty
            <p class="meta">Für diese Leistung ist keine Stelle der Gemeinde zuständig.</p>
        @endforelse
        @foreach ($contacts as $person) @include('public.partials.contact-card', ['contact' => $person]) @endforeach
        @if ($place)
            <div class="aside-section">
                <h2 class="aside-heading">{{ $place->displayTitle() }}</h2>
                @include('public.partials.location', ['location' => $place, 'showName' => false])
            </div>
        @endif
        @if ($mode !== \App\Enums\OnlineServiceMode::NotSpecified)
            <div class="aside-section">
                <h2 class="aside-heading">Online</h2>
                <p>{{ $mode->label() }}@if ($mode === \App\Enums\OnlineServiceMode::Unavailable) – bitte persönlich vorsprechen.@endif</p>
                @if ($online?->privacy_note && $actionable)<p class="meta">{{ $online->privacy_note }}</p>@endif
            </div>
        @endif
    </aside>
</div>
