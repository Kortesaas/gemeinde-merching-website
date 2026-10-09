@php
    $seo ??= app(\App\Services\Seo\SeoMetadata::class)->forModel($model ?? null, request()->getPathInfo());
    $siteTitle = $seo->siteName;
    $robots ??= $seo->robots;
    $settings = app(\App\Services\Settings\SiteConfiguration::class)->current();
    $nav = app(\App\Services\Navigation\NavigationManager::class);
    $mainNav = $nav->tree(\App\Enums\NavigationMenu::Main);
    $trail = $nav->trail($mainNav, rawurldecode(request()->getPathInfo()));
    $footerNav = $nav->tree(\App\Enums\NavigationMenu::Footer);
    $townHall = $settings?->townHall;
    $central = $settings?->centralDepartment;
@endphp
@extends('layouts.base', ['viteEntries' => ['resources/css/app.css', 'resources/js/app.js'], 'robots' => $robots])
@section('body')
<header class="site-header">
    <div class="container">
        <nav class="utility-nav" aria-label="Service"><a href="{{ route('public.contact') }}">Kontakt</a></nav>
        <div class="header-main">
            <a class="brand" href="{{ route('public.home') }}">
                <img src="{{ \Illuminate\Support\Facades\Vite::asset(config('public.wappen')) }}" alt="" width="667" height="693">
                <span><strong>{{ $siteTitle }}</strong><small>Gemeinde · Bürgerservice · Rathaus</small></span>
            </a>
            <details class="site-navigation" id="site-navigation" open data-navigation>
                <summary>Menü</summary>
                <nav aria-label="Hauptnavigation">@include('public.partials.navigation', ['nodes' => $mainNav])</nav>
            </details>
            <div class="header-actions"><a class="button button--secondary" href="{{ route('public.search') }}" data-search-trigger>Suchen</a></div>
        </div>
    </div>
</header>
<main id="inhalt" class="container page-main" tabindex="-1">
    @if (request()->getPathInfo() !== '/')
        <nav class="breadcrumbs" aria-label="Brotkrümelnavigation"><ol><li><a href="{{ route('public.home') }}">Startseite</a></li>@foreach(array_slice($trail, 0, -1) as $ancestor)<li><a href="{{ $ancestor->item->href() }}">{{ $ancestor->item->label }}</a></li>@endforeach<li aria-current="page">{{ ($model ?? null)?->displayTitle() ?? ($section['title'] ?? trim($__env->yieldContent('title'))) }}</li></ol></nav>
    @endif
    @yield('content')
</main>
<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <section aria-label="Kontakt zur Gemeinde"><p><strong>{{ $siteTitle }}</strong></p>
                @if ($townHall?->isPubliclyReachable())<p>{{ $townHall->street }}<br>{{ $townHall->postal_code }} {{ $townHall->city }}</p>@if ($townHall->opening_hours)<p>{{ $townHall->opening_hours }}</p>@endif @endif
                @if ($central?->isPubliclyReachable()) @include('public.partials.contact-data', ['contact' => $central]) @endif
                <a href="{{ route('public.contact') }}">Nachricht schreiben</a>
            </section>
            <nav aria-label="Fußzeile"><ul class="footer-links">@foreach ($footerNav as $node)<li>@include('public.partials.navigation', ['nodes' => [$node]])</li>@endforeach</ul></nav>
        </div>
        <div class="footer-bottom">© {{ $siteTitle }}</div>
    </div>
</footer>
<dialog class="search-panel" id="search-panel" aria-labelledby="search-panel-title" data-search-dialog>
    <div class="panel-heading"><h2 id="search-panel-title">Wonach suchen Sie?</h2><button class="button button--secondary" type="button" data-dialog-close>Suche schließen</button></div>
    @include('public.partials.search-form', ['searchId' => 'overlay-search'])
    <a href="{{ route('public.search') }}">Alle Suchergebnisse anzeigen</a>
</dialog>
@endsection
