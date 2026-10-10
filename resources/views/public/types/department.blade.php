@php
    $people = $model->people()->where('is_active', true)->get();
    $services = $model->services()->with('canonicalRoute')->visible()->orderBy('title')->get()->filter(fn ($s) => $s->publicPath() !== null);
    $location = $model->location?->isPubliclyReachable() ? $model->location : null;
@endphp
@include('public.partials.page-header', ['eyebrow' => 'Verwaltung'.($model->short_name ? ' · '.$model->short_name : ''), 'lead' => $model->description])
<div class="content-layout">
    <aside class="content-aside" aria-labelledby="dept-contact">
        <h2 id="dept-contact" class="aside-heading">Kontakt</h2>
        @include('public.partials.contact-data', ['contact' => $model])
        @if ($model->opening_hours)<h3 class="aside-subheading">Sprechzeiten</h3><ul class="plain-list">@foreach (\App\Support\Content\PublicFormat::lines($model->opening_hours) as $line)<li>{{ $line }}</li>@endforeach</ul>@endif
        @if ($location)<div class="aside-section"><h2 class="aside-heading">Adresse</h2>@include('public.partials.location', ['location' => $location, 'showHours' => ! $model->opening_hours])</div>@endif
        <p class="aside-section"><a href="{{ route('public.contact') }}">Nachricht schreiben</a></p>
    </aside>
    <div class="content-main">
        @if ($people->isNotEmpty())
            <section class="content-section" aria-labelledby="people"><h2 id="people">Ansprechpersonen</h2>
                <div class="contact-grid">
                    @foreach ($people as $person)@include('public.partials.contact-card', ['contact' => $person, 'headingTag' => 'h3', 'role' => $person->pivot?->function_label ? $person->pivot->function_label.' · '.$person->job_title : null, 'showResponsibilities' => true])@endforeach
                </div>
            </section>
        @endif
        @if ($services->isNotEmpty())
            <section class="content-section" aria-labelledby="services"><h2 id="services">Leistungen</h2>
                <ul class="link-grid link-grid--compact">
                    @foreach ($services as $service)<li><a class="link-row" href="{{ \App\Support\Routing\PublicPath::toUrl($service->publicPath()) }}"><span class="link-row__text"><span class="link-row__title">{{ $service->displayTitle() }}</span></span><x-icon name="arrow-right" class="link-row__arrow" /></a></li>@endforeach
                </ul>
            </section>
        @endif
    </div>

</div>
