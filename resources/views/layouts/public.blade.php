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
    $sectionCrumb = isset($model) && $trail === [] ? \App\Support\Content\PublicFormat::section($model) : null;
    $pageLabel = ($model ?? null)?->displayTitle() ?? ($section['title'] ?? trim($__env->yieldContent('title')));
@endphp
@extends('layouts.base', ['viteEntries' => ['resources/css/app.css', 'resources/js/app.js'], 'robots' => $robots])
@section('body')
<header class="site-header" data-site-header>
    <div class="container site-header__inner">
        @include('public.partials.brand')
        <a class="search-trigger search-trigger--compact" href="{{ route('public.search') }}" data-search-trigger><x-icon name="search" /><span class="visually-hidden">Suchen</span></a>
        <details class="site-navigation" id="site-navigation" open data-navigation>
            <summary class="menu-toggle" aria-label="Menü"><x-icon name="menu" class="menu-toggle__open" /><x-icon name="close" class="menu-toggle__close" /><span class="menu-toggle__label">Menü</span></summary>
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
    <aside class="alert-band alert-band--{{ $alert->severity->value }}" aria-label="Aktueller Hinweis" data-alert>
        <div class="container alert-band__inner">
            <x-icon :name="$alert->severity === \App\Enums\AlertSeverity::Info ? 'info' : 'warning'" />
            <p><strong>{{ $alert->title }}</strong> @if ($alert->body)<span class="alert-band__body">{{ $alert->body }}</span>@endif
                @if ($alert->link_url)<a href="{{ $alert->link_url }}">{{ $alert->link_label ?: 'Weitere Informationen' }}</a>@endif</p>
            {{-- Shown by JavaScript only; hides the notice for this page view without storing anything. --}}
            <button class="alert-band__close" type="button" data-alert-dismiss hidden><x-icon name="close" /><span class="visually-hidden">Hinweis „{{ $alert->title }}“ ausblenden</span></button>
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
                <h2 id="footer-contact-heading" class="footer-heading footer-brand"><img src="{{ \Illuminate\Support\Facades\Vite::asset(config('public.wappen')) }}" alt="" width="667" height="693">{{ $siteTitle }}</h2>
                @if ($townHall)
                    <p>{{ $townHall->displayTitle() }} · {{ $townHall->street }}<br>{{ $townHall->postal_code }} {{ $townHall->city }}</p>
                    @if ($townHall->opening_hours)<p class="footer-hours">@foreach (\App\Support\Content\PublicFormat::lines($townHall->opening_hours) as $line){{ $line }}@if (! $loop->last)<br>@endif @endforeach</p>@endif
                @endif
                <ul class="footer-contact__links">
                    @if ($central?->phone)<li><a href="{{ \App\Support\Content\PublicFormat::phoneHref($central->phone) }}"><x-icon name="phone" /> {{ $central->phone }}</a></li>@endif
                    <li><a href="{{ route('public.contact') }}"><x-icon name="mail" /> Nachricht schreiben</a></li>
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
            <div class="display-actions">
                <button class="button button--ghost" type="button" data-read-stop hidden>Vorlesen stoppen</button>
            </div>
            <a href="#inhalt" class="footer-top">Zum Seitenanfang</a>
        </div>
        <noscript><p class="display-noscript">Für die Darstellungshilfen benötigen Sie JavaScript. Die Website bleibt vollständig nutzbar; die Schrift können Sie mit der Zoomfunktion Ihres Browsers vergrößern.</p></noscript>
    </div>
</footer>
<dialog class="search-panel" id="search-panel" aria-labelledby="search-panel-title" data-search-dialog>
    <div class="search-panel__bar">
        <div class="container search-panel__bar-inner">
            @include('public.partials.brand', ['brandTag' => 'div'])
            <button class="button button--ghost" type="button" aria-label="Suche schließen" data-dialog-close><x-icon name="close" /> <span class="search-panel__close-long">Suche schließen</span><span class="search-panel__close-short">Schließen</span></button>
        </div>
    </div>
    <div class="search-panel__body">
        <h2 id="search-panel-title">Suche</h2>
        <p class="search-panel__intro">Leistungen, Kontakte und Meldungen finden.</p>
        @include('public.partials.search-form', ['searchId' => 'overlay-search', 'pill' => true, 'searchLabel' => 'Suchbegriff'])
        <div class="search-panel__footer">
            <a href="{{ route('public.search') }}" data-search-all>Alle Ergebnisse anzeigen</a>
            <p class="search-panel__hint">Pfeiltasten wählen · Eingabetaste öffnet · Esc schließt</p>
        </div>
        @php $searchShortcuts = array_slice($nav->tree(\App\Enums\NavigationMenu::Service), 0, 5); @endphp
        @if ($searchShortcuts)
            <nav class="search-panel__shortcuts" aria-labelledby="search-shortcuts-title" data-search-shortcuts>
                <h3 id="search-shortcuts-title">Häufig gesucht</h3>
                <ul>@foreach ($searchShortcuts as $shortcut)<li><a href="{{ $shortcut->href }}">{{ $shortcut->item->label }} <x-icon name="arrow-right" /></a></li>@endforeach</ul>
            </nav>
        @endif
    </div>
</dialog>
<button class="button display-trigger display-launcher" type="button" data-display-trigger aria-label="Darstellung &amp; Barrierefreiheit" title="Darstellung &amp; Barrierefreiheit" aria-haspopup="dialog" aria-controls="display-panel" aria-expanded="false" hidden><x-icon name="eye" /></button>
@include('public.partials.display-panel')
@endsection
