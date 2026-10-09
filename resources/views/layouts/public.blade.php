{{-- Temporary public layout. The real header/navigation/footer follow with the design system. --}}
@php
    $seo ??= app(\App\Services\Seo\SeoMetadata::class)->forModel($model ?? null, request()->getPathInfo());
    $siteTitle = $seo->siteName;
    $robots ??= $seo->robots;
@endphp
@extends('layouts.base', [
    'viteEntries' => ['resources/css/app.css'],
    'robots' => $robots,
])

@section('body')
    <header class="page-header">
        <div class="container">
            <p class="page-header__name"><a href="{{ route('public.home') }}">{{ $siteTitle }}</a></p>
        </div>
    </header>

    <main id="inhalt" class="container page-main" tabindex="-1">
        @yield('content')
    </main>

    <footer class="page-footer">
        <div class="container">
            <p>{{ $siteTitle }}</p>
        </div>
    </footer>
@endsection
