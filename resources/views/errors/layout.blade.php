{{-- Minimal, generic error page. Never shows exception details, paths or SQL. --}}
@extends('layouts.base', ['viteEntries' => ['resources/css/app.css'], 'robots' => 'noindex, nofollow'])

@section('body')
    <main id="inhalt" class="container page-main" tabindex="-1">
        <h1>@yield('heading')</h1>
        <p>@yield('message')</p>
        <p><a href="{{ url('/') }}">Zur Startseite</a></p>
    </main>
@endsection
