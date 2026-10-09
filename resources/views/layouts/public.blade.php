@php
    $seo ??= app(\App\Services\Seo\SeoMetadata::class)->forModel($model ?? null, request()->getPathInfo());
    $siteTitle = $seo->siteName;
    $robots ??= $seo->robots;
    $settings = app(\App\Services\Settings\SiteConfiguration::class)->current();
    $nav = app(\App\Services\Navigation\NavigationManager::class);
    $mainNav = $nav->tree(\App\Enums\NavigationMenu::Main);
    $currentPath = rawurldecode(request()->getPathInfo());
    $trail = $nav->trail($mainNav, $currentPath);
    $footerNav = $nav->tree(\App\Enums\NavigationMenu::Footer);
    $townHall = $settings?->townHall;
    $townHall = $townHall?->isPubliclyReachable() ? $townHall : null;
    $central = $settings?->centralDepartment;
    $central = $central?->isPubliclyReachable() ? $central : null;
    $isHome = $currentPath === '/';
    $alerts = \App\Models\SiteAlert::query()->visible()->orderByDesc('publish_at')->get()
        ->filter(fn ($alert) => $isHome || $alert->severity === \App\Enums\AlertSeverity::Critical);
    // Development-only hint: never rendered in production or without demo records.
    $demoContent = app()->environment(['local', 'development']) && \App\Models\SourceReference::query()->where('source_system', 'development-demo')->exists();
    $sectionCrumb = isset($model) && $trail === [] ? \App\Support\Content\PublicFormat::section($model) : null;
    $pageLabel = ($model ?? null)?->displayTitle() ?? ($section['title'] ?? trim($__env->yieldContent('title')));
@endphp
@extends('layouts.base', ['viteEntries' => ['resources/css/app.css', 'resources/js/app.js'], 'robots' => $robots])
@section('body')
@if ($demoContent)
    <aside class="demo-ribbon" aria-label="Demonstrationsumgebung"><p>Demonstrationsumgebung: Alle Inhalte, Namen, Telefonnummern, Termine und Beträge sind frei erfundene Beispieldaten.</p></aside>
@endif
<header class="site-header" data-site-header>
    <div class="container site-header__inner">
        @include('public.partials.brand')
        <nav class="utility-nav" aria-label="Service">
            <ul>
                <li><a href="{{ route('public.contact') }}">Kontakt</a></li>
            </ul>
        </nav>
        <a class="search-trigger search-trigger--compact" href="{{ route('public.search') }}" data-search-trigger><x-icon name="search" /><span class="visually-hidden">Suchen</span></a>
        <details class="site-navigation" id="site-navigation" open data-navigation>
            <summary class="menu-toggle"><x-icon name="menu" class="menu-toggle__open" /><x-icon name="close" class="menu-toggle__close" /><span class="menu-toggle__label">Menü</span></summary>
            <div class="site-navigation__panel">
                <div class="site-navigation__search">
                    @include('public.partials.search-form', ['searchId' => 'menu-search', 'searchLabel' => 'Website durchsuchen', 'pill' => true, 'suggest' => false])
                </div>
                <nav class="main-nav" aria-label="Hauptnavigation">
                    @include('public.partials.navigation', ['nodes' => $mainNav])
                </nav>
                <div class="site-navigation__extras">
                    <ul class="mobile-utility">
                        <li><a href="{{ route('public.contact') }}">Kontakt</a></li>
                    </ul>
                    @if ($townHall)
                        <div class="menu-contact">
                            <p class="menu-contact__title">{{ $townHall->displayTitle() }}</p>
                            @if ($townHall->opening_hours)<p>{{ implode(' · ', \App\Support\Content\PublicFormat::lines($townHall->opening_hours)) }}</p>@endif
                            @if ($central?->phone)<a class="button button--block" href="{{ \App\Support\Content\PublicFormat::phoneHref($central->phone) }}"><x-icon name="phone" /> {{ $central->phone }} anrufen</a>@endif
                        </div>
                    @endif
                </div>
            </div>
        </details>
        <a class="search-trigger search-trigger--wide" href="{{ route('public.search') }}" data-search-trigger><x-icon name="search" /><span>Suchen</span></a>
    </div>
</header>
@foreach ($alerts as $alert)
    <aside class="alert-band alert-band--{{ $alert->severity->value }}" aria-label="Aktueller Hinweis">
        <div class="container alert-band__inner">
            <x-icon :name="$alert->severity === \App\Enums\AlertSeverity::Info ? 'info' : 'warning'" />
            <p><strong>{{ $alert->title }}</strong> @if ($alert->body)<span class="alert-band__body">{{ $alert->body }}</span>@endif
                @if ($alert->link_url)<a href="{{ $alert->link_url }}">{{ $alert->link_label ?: 'Weitere Informationen' }}</a>@endif</p>
        </div>
    </aside>
@endforeach
<main id="inhalt" class="page-main {{ $isHome ? 'page-main--home' : '' }}" tabindex="-1">
    <div class="container">
        @unless ($isHome)
            <nav class="breadcrumbs" aria-label="Brotkrümelnavigation">
                <ol>
                    <li><a href="{{ route('public.home') }}">Startseite</a></li>
                    @if ($sectionCrumb)<li><a href="{{ $sectionCrumb['url'] }}">{{ $sectionCrumb['label'] }}</a></li>@endif
                    @foreach (array_slice($trail, 0, -1) as $ancestor)<li><a href="{{ $ancestor->href }}">{{ $ancestor->item->label }}</a></li>@endforeach
                    <li aria-current="page">{{ $pageLabel }}</li>
                </ol>
            </nav>
        @endunless
        @yield('content')
    </div>
</main>
<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <section class="footer-contact" aria-labelledby="footer-contact-heading">
                <h2 id="footer-contact-heading" class="footer-heading">{{ $siteTitle }}</h2>
                @if ($townHall)
                    <p>{{ $townHall->displayTitle() }} · {{ $townHall->street }}<br>{{ $townHall->postal_code }} {{ $townHall->city }}</p>
                    @if ($townHall->opening_hours)<p class="footer-hours">@foreach (\App\Support\Content\PublicFormat::lines($townHall->opening_hours) as $line){{ $line }}@if (! $loop->last)<br>@endif @endforeach</p>@endif
                @endif
                <ul class="footer-contact__links">
                    @if ($central?->phone)<li><a href="{{ \App\Support\Content\PublicFormat::phoneHref($central->phone) }}">{{ $central->phone }}</a></li>@endif
                    @if ($central?->email)<li><a href="mailto:{{ $central->email }}">{{ $central->email }}</a></li>@endif
                    <li><a href="{{ route('public.contact') }}">Nachricht schreiben</a></li>
                </ul>
            </section>
            @foreach ($footerNav as $group)
                <nav class="footer-group" aria-labelledby="footer-group-{{ $loop->index }}">
                    <h2 class="footer-heading" id="footer-group-{{ $loop->index }}"><a href="{{ $group->href }}">{{ $group->item->label }}</a></h2>
                    @if ($group->children)
                        <ul>
                            @foreach ($group->children as $child)
                                <li><a href="{{ $child->href }}">{{ $child->item->label }}@if ($child->isExternal())<span class="visually-hidden"> (externer Link)</span><x-icon name="external" class="icon--inline" />@endif</a></li>
                            @endforeach
                        </ul>
                    @endif
                </nav>
            @endforeach
        </div>
        <div class="footer-bottom">
            <p>© {{ \App\Support\SiteTime::now()->format('Y') }} {{ $siteTitle }}</p>
            <a href="#inhalt" class="footer-top">Zum Seitenanfang</a>
        </div>
    </div>
</footer>
<dialog class="search-panel" id="search-panel" aria-labelledby="search-panel-title" data-search-dialog>
    <div class="search-panel__bar">
        <div class="container search-panel__bar-inner">
            @include('public.partials.brand', ['brandTag' => 'div'])
            <button class="button button--ghost" type="button" data-dialog-close><x-icon name="close" /> Suche schließen</button>
        </div>
    </div>
    <div class="search-panel__body">
        <h2 id="search-panel-title">Wonach suchen Sie?</h2>
        @include('public.partials.search-form', ['searchId' => 'overlay-search', 'pill' => true, 'searchLabel' => 'Suchbegriff'])
        <div class="search-panel__footer">
            <a href="{{ route('public.search') }}" data-search-all>Alle Ergebnisse anzeigen</a>
            <p class="search-panel__hint">Pfeiltasten wählen · Eingabetaste öffnet · Esc schließt</p>
        </div>
    </div>
</dialog>
@endsection
