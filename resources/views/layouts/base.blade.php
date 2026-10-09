{{--
    Base document for every page (public website, backend, error pages).
    Accessibility: lang="de", zoomable viewport, skip link, one <main id="inhalt">.
    CSP: no inline <script>/<style> and no style="" attributes anywhere.
--}}
@php
    $hasErrors = isset($errors) && $errors->any();
    $pageTitle = $seo->title ?? trim($__env->yieldContent('title'));
    $name = $siteTitle ?? config('app.name');
    $documentTitle = ($hasErrors ? 'Fehler: ' : '').($pageTitle !== '' && $pageTitle !== $name ? $pageTitle.' – ' : '').$name;
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $documentTitle }}</title>
    <meta name="robots" content="{{ $robots ?? 'noindex, nofollow' }}">
    @hasSection('canonical')
        {{-- Absolute production URL; slashless except for the root. --}}
        <link rel="canonical" href="@yield('canonical')">
    @endif
    @isset($seo)
        @if ($seo->description)<meta name="description" content="{{ $seo->description }}">@endif
        <meta property="og:title" content="{{ $documentTitle }}">
        <meta property="og:type" content="{{ $seo->type }}">
        <meta property="og:site_name" content="{{ $seo->siteName }}">
        <meta property="og:locale" content="{{ config('seo.locale') }}">
        @if ($seo->canonical)<meta property="og:url" content="{{ $seo->canonical }}">@endif
        @if ($seo->description)<meta property="og:description" content="{{ $seo->description }}">@endif
        <meta property="og:image" content="{{ $seo->image['url'] }}">
        <meta property="og:image:secure_url" content="{{ $seo->image['url'] }}">
        <meta property="og:image:width" content="{{ $seo->image['width'] }}">
        <meta property="og:image:height" content="{{ $seo->image['height'] }}">
        <meta property="og:image:type" content="{{ $seo->image['type'] }}">
        <meta property="og:image:alt" content="{{ $seo->image['alt'] }}">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $documentTitle }}">
        <meta name="twitter:description" content="{{ $seo->description }}">
        <meta name="twitter:image" content="{{ $seo->image['url'] }}">
        <meta name="twitter:image:alt" content="{{ $seo->image['alt'] }}">
        <link rel="sitemap" type="application/xml" href="{{ \App\Support\Content\SeoUrl::path('/sitemap.xml') }}">
        @unless ($__env->hasSection('canonical'))
            @if ($seo->canonical)<link rel="canonical" href="{{ $seo->canonical }}">@endif
        @endunless
    @endisset
    {{-- Root-relative browser assets stay self-hosted in both local previews and production. --}}
    <link rel="icon" href="/favicon.ico" sizes="16x16 32x32 48x48" type="image/vnd.microsoft.icon">
    <link rel="icon" href="/identity/favicon-32.png" sizes="32x32" type="image/png">
    <link rel="icon" href="/identity/favicon.svg" sizes="any" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/identity/apple-touch-icon.png" sizes="180x180">
    <link rel="mask-icon" href="/identity/safari-pinned-tab.svg" color="#0b5cc2">
    <link rel="manifest" href="/site.webmanifest">
    <meta name="theme-color" content="#0b5cc2">
    <meta name="application-name" content="{{ $seo->siteName ?? config('app.name') }}">
    <meta name="apple-mobile-web-app-title" content="{{ $seo->siteName ?? config('app.name') }}">
    @vite($viteEntries ?? ['resources/css/app.css'])
</head>
<body>
    <a class="skip-link" href="#inhalt">Zum Inhalt springen</a>
    @if (isset($seo) && $seo->canonical && ! request()->is('suche', 'kontakt', 'kontakt/*'))
        @include('public.partials.schema', ['schema' => app(\App\Services\Seo\StructuredData::class)->page($seo, $pageTitle ?: $seo->siteName, $model ?? null)])
    @endif
    @yield('body')
</body>
</html>
