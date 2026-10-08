{{--
    Base document for every page (public website, backend, error pages).
    Accessibility: lang="de", zoomable viewport, skip link, one <main id="inhalt">.
    CSP: no inline <script>/<style> and no style="" attributes anywhere.
--}}
@php
    $hasErrors = isset($errors) && $errors->any();
    $pageTitle = trim($__env->yieldContent('title'));
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
    {{-- Suppresses the automatic /favicon.ico request until the real icon exists. --}}
    <link rel="icon" href="data:,">
    @vite($viteEntries ?? ['resources/css/app.css'])
</head>
<body>
    <a class="skip-link" href="#inhalt">Zum Inhalt springen</a>
    @yield('body')
</body>
</html>
