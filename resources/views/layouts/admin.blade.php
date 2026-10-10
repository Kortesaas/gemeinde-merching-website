@extends('layouts.base', ['viteEntries' => ['resources/css/admin.css', 'resources/js/app.js'], 'robots' => 'noindex, nofollow', 'siteTitle' => 'Verwaltung – '.config('app.name')])
@section('body')
@auth
@php
    $user = auth()->user();
    $role = collect(\App\Support\Authorization\Role::cases())->first(fn ($r) => $user->hasRole($r->value));
    $initials = collect(preg_split('/\s+/', preg_replace('/\(.*?\)/', '', $user->name)))->filter()->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('');
    $reviewCount = \App\Models\ContentProposal::query()->where('status', \App\Enums\ProposalStatus::Submitted)->where(fn ($q) => $q->whereNull('author_id')->orWhere('author_id', '!=', $user->getKey()))->count();
    $groups = [
        'Inhalte' => ['article', 'event', 'notice', 'page', 'site-alert'],
        'Verwaltungsdaten' => ['service', 'life-situation', 'department', 'person', 'organization', 'location', 'wahlperioden', 'ratsmitglieder', 'ausschuesse'],
        'Dateien' => ['document', 'budget-plan', 'media', 'gallery', 'external-resource'],
        'System' => ['navigation', 'redirect', 'kategorien', 'schlagwoerter', 'search-synonym', 'site-settings', 'contact-route'],
    ];
    $allResources = \App\Admin\ResourceRegistry::all();
@endphp
<div class="cms-shell">
    <aside class="cms-sidebar">
        <a class="cms-brand" href="{{ route('admin.dashboard') }}">
            <img src="{{ \Illuminate\Support\Facades\Vite::asset(config('public.wappen')) }}" alt="" width="667" height="693">
            <span><strong>{{ config('app.name') }}</strong><small>Verwaltung</small></span>
        </a>
        <details class="cms-navigation" open data-cms-navigation>
            <summary><x-icon name="menu" /> <span class="cms-menu-label">Redaktionsmenü</span><span class="cms-menu-label--short">Menü</span></summary>
            <nav aria-label="Verwaltung">
                <h2>Übersicht</h2>
                <ul>
                    @can(\App\Support\Authorization\Permission::AccessAdmin->value)
                        <li><a href="{{ route('admin.dashboard') }}" @if (request()->routeIs('admin.dashboard')) aria-current="page" @endif>Dashboard</a></li>
                        <li><a href="{{ route('admin.overview') }}" @if (request()->routeIs('admin.overview')) aria-current="page" @endif>Alle Inhalte</a></li>
                    @endcan
                    <li><a href="{{ route('admin.proposals.index') }}" @if (request()->routeIs('admin.proposals.*')) aria-current="page" @endif>Freigaben @if ($reviewCount > 0)<span class="nav-count">{{ $reviewCount }}<span class="visually-hidden"> offen</span></span>@endif</a></li>
                </ul>
                @foreach ($groups as $heading => $keys)
                    <h2>{{ $heading }}</h2>
                    <ul>
                        @foreach ($keys as $key)
                            @isset($allResources[$key])
                                @can('viewAny', $allResources[$key]->model())
                                    <li><a href="{{ route('admin.'.$key.'.index') }}" @if (request()->routeIs('admin.'.$key.'.*')) aria-current="page" @endif>{{ $allResources[$key]->pluralLabel() }}</a></li>
                                @endcan
                            @endisset
                        @endforeach
                    </ul>
                @endforeach
                @can('viewAny', \App\Models\User::class)
                    <h2>Konten</h2>
                    <ul><li><a href="{{ route('admin.user.index') }}" @if (request()->routeIs('admin.user.*')) aria-current="page" @endif>Benutzerkonten</a></li></ul>
                @endcan
            </nav>
        </details>
    </aside>
    <div class="cms-content">
        <header class="cms-topbar">
            @can(\App\Support\Authorization\Permission::AccessAdmin->value)
                <form class="cms-search" method="GET" action="{{ route('admin.overview') }}" role="search">
                    <label class="visually-hidden" for="cms-search">Inhalte durchsuchen</label>
                    <x-icon name="search" />
                    <input id="cms-search" name="q" type="search" placeholder="Inhalte, Dokumente, Personen …" value="{{ request()->routeIs('admin.overview') ? request('q') : '' }}" autocomplete="off">
                </form>
            @endcan
            <a class="cms-topbar__site" href="{{ route('public.home') }}">Website ansehen<span class="visually-hidden"> (öffnet die öffentliche Startseite)</span><x-icon name="external" class="icon--inline" /></a>
            <details class="cms-account">
                <summary aria-label="Konto von {{ $user->name }}">
                    <span class="cms-avatar" aria-hidden="true">{{ $initials ?: '?' }}</span>
                    <span class="cms-account__text"><span class="cms-account__name">{{ $user->name }}</span><span class="cms-account__role">{{ $role?->label() ?? 'Redaktion' }}</span></span>
                </summary>
                <div class="cms-account__menu">
                    <a href="{{ route('admin.two-factor.setup') }}">Konto und Sicherheit</a>
                    <form method="POST" action="{{ route('admin.logout') }}">@csrf<button type="submit"><x-icon name="logout" /> Abmelden</button></form>
                </div>
            </details>
        </header>
        <main id="inhalt" class="cms-main" tabindex="-1">@yield('content')</main>
    </div>
</div>
@else
    <header class="auth-header">
        <img src="{{ \Illuminate\Support\Facades\Vite::asset(config('public.wappen')) }}" alt="" width="667" height="693">
        <span><strong>{{ config('app.name') }}</strong><small>Verwaltung Login</small></span>
    </header>
    <main id="inhalt" class="auth-main" tabindex="-1">@yield('content')</main>
@endauth
@endsection
