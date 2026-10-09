{{-- Database-independent shell also works when the application is unavailable. --}}
@extends('layouts.base', ['viteEntries'=>['resources/css/app.css'], 'robots'=>'noindex, nofollow'])
@section('body')
<header class="site-header"><div class="container header-main"><a class="brand" href="{{ url('/') }}"><strong>{{ config('app.name') }}</strong></a></div></header>
<main id="inhalt" class="container page-main" tabindex="-1"><section class="hero"><p class="eyebrow">{{ config('app.name') }}</p><h1>@yield('heading')</h1><p class="lead">@yield('message')</p><a class="button" href="{{ url('/') }}">Zur Startseite</a> <a href="{{ route('public.search') }}">Website durchsuchen</a></section></main>
<footer class="site-footer"><div class="container">{{ config('app.name') }}</div></footer>
@endsection
