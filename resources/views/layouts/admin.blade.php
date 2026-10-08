{{-- Temporary backend layout (functional only; visual design follows later). --}}
@extends('layouts.base', [
    'viteEntries' => ['resources/css/admin.css'],
    'robots' => 'noindex, nofollow',
    'siteTitle' => 'Verwaltung – '.config('app.name'),
])

@section('body')
    <header class="page-header">
        <div class="container page-header__inner">
            <p class="page-header__name">{{ config('app.name') }} – Verwaltung</p>

            @auth
                <nav aria-label="Verwaltung">
                    <ul class="nav-list">
                        @can(\App\Support\Authorization\Permission::AccessAdmin->value)
                            <li><a href="{{ route('admin.dashboard') }}" @if (request()->routeIs('admin.dashboard')) aria-current="page" @endif>Dashboard</a></li>
                        @endcan
                        <li><a href="{{ route('admin.two-factor.setup') }}" @if (request()->routeIs('admin.two-factor.*')) aria-current="page" @endif>Zwei-Faktor-Authentisierung</a></li>
                        <li>
                            <form method="POST" action="{{ route('admin.logout') }}">
                                @csrf
                                <button type="submit" class="button button--secondary">Abmelden</button>
                            </form>
                        </li>
                    </ul>
                </nav>
            @endauth
        </div>
    </header>

    <main id="inhalt" @class(['container', 'page-main', 'page-main--narrow' => ! request()->routeIs('admin.*.index', 'admin.*.edit', 'admin.*.create', 'admin.*.revisions', 'admin.*.revisions.show', 'admin.dashboard')]) tabindex="-1">
        @yield('content')
    </main>
@endsection
