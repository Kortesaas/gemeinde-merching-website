{{-- Database-independent shell also works when the application is unavailable. --}}
@extends('layouts.base', ['viteEntries'=>['resources/css/app.css'], 'robots'=>'noindex, nofollow'])
@section('body')
<header class="site-header"><div class="container site-header__inner site-header__inner--simple">@include('public.partials.brand')</div></header>
<main id="inhalt" class="page-main" tabindex="-1">
    <div class="container error-page">
        <p class="eyebrow">Fehler @yield('code')</p>
        <h1>@yield('heading')</h1>
        <p class="lead">@yield('message')</p>
        <form class="search-form search-form--pill" method="GET" action="/suche" role="search">
            <label class="search-form__label" for="error-search">Website durchsuchen</label>
            <div class="search-form__field"><input class="search-form__input" id="error-search" name="q" type="search" maxlength="150"><button class="button search-form__submit" type="submit">Suchen</button></div>
        </form>
        <ul class="inline-links"><li><a href="{{ url('/') }}">Zur Startseite</a></li><li><a href="{{ url('/buergerservice') }}">Bürgerservice</a></li><li><a href="{{ url('/kontakt') }}">Kontakt</a></li></ul>
    </div>
</main>
<footer class="site-footer site-footer--simple"><div class="container"><p>{{ config('app.name') }}</p></div></footer>
@endsection
