{{--
    Base document for every page (public website, backend, error pages).
    Accessibility: lang="de", zoomable viewport, skip link, one <main id="inhalt">.
    CSP: no inline <script>/<style> and no style="" attributes anywhere.
--}}
@php
    $hasErrors = isset($errors) && $errors->any();
    $pageTitle = $seo->title ?? trim($__env->yieldContent('title'));
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $hasErrors ? 'Fehler: ' : '' }}{{ $pageTitle !== '' ? $pageTitle.' – ' : '' }}{{ $siteTitle ?? config('app.name') }}</title>
    <meta name="robots" content="{{ $robots ?? 'noindex, nofollow' }}">
    @hasSection('canonical')
        {{-- Absolute, slashless canonical URL on the canonical host (APP_URL). --}}
        <link rel="canonical" href="@yield('canonical')">
    @endif
    @isset($seo)
        @if ($seo->description)<meta name="description" content="{{ $seo->description }}">@endif
        <meta property="og:title" content="{{ $pageTitle !== '' ? $pageTitle : $seo->siteName }}">
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ $seo->siteName }}">
        @if ($seo->canonical)<meta property="og:url" content="{{ $seo->canonical }}">@endif
        @if ($seo->description)<meta property="og:description" content="{{ $seo->description }}">@endif
        @unless ($__env->hasSection('canonical'))
            @if ($seo->canonical)<link rel="canonical" href="{{ $seo->canonical }}">@endif
        @endunless
    @endisset
    {{-- Suppresses the automatic /favicon.ico request until the real icon exists. --}}
    <link rel="icon" href="data:,">
    @vite($viteEntries ?? ['resources/css/app.css'])
</head>
<body>
    <a class="skip-link" href="#inhalt">Zum Inhalt springen</a>
    @yield('body')
</body>
</html>
