{{-- Temporary public layout. The real header/navigation/footer follow with the design system. --}}
@extends('layouts.base', [
    'viteEntries' => ['resources/css/app.css'],
    'robots' => \App\Support\SearchEngineIndexing::robotsDirective(),
])

@section('body')
    <header class="page-header">
        <div class="container">
            <p class="page-header__name"><a href="{{ route('public.home') }}">{{ config('app.name') }}</a></p>
        </div>
    </header>

    <main id="inhalt" class="container page-main" tabindex="-1">
        @yield('content')
    </main>

    <footer class="page-footer">
        <div class="container">
            <p>{{ config('app.name') }}</p>
        </div>
    </footer>
@endsection
