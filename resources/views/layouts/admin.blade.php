@extends('layouts.base', ['viteEntries' => ['resources/css/admin.css', 'resources/js/app.js'], 'robots' => 'noindex, nofollow', 'siteTitle' => 'Verwaltung – '.config('app.name')])
@section('body')
@auth
<div class="cms-shell">
    <aside class="cms-sidebar">
        <a class="cms-brand" href="{{ route('admin.dashboard') }}"><strong>{{ config('app.name') }}</strong><small>Redaktion</small></a>
        <details open data-cms-navigation><summary>Redaktionsmenü</summary>
            <nav aria-label="Verwaltung">
                <h2>Übersicht</h2><ul>
                    @can(\App\Support\Authorization\Permission::AccessAdmin->value)<li><a href="{{ route('admin.dashboard') }}" @if(request()->routeIs('admin.dashboard')) aria-current="page" @endif>Dashboard</a></li><li><a href="{{ route('admin.overview') }}" @if(request()->routeIs('admin.overview')) aria-current="page" @endif>Alle Inhalte</a></li>@endcan
                    <li><a href="{{ route('admin.proposals.index') }}" @if(request()->routeIs('admin.proposals.*')) aria-current="page" @endif>Freigaben und Vorschläge</a></li>
                </ul>
                @php $groups = ['Inhalte'=>['article','event','page','notice','site-alert'], 'Verwaltungsdaten'=>['service','life-situation','department','person','organization','location','wahlperioden','ratsmitglieder','ausschuesse'], 'Dateien'=>['document','media','gallery','external-resource'], 'System'=>['navigation','redirect','kategorien','schlagwoerter','search-synonym','site-settings','contact-route']]; $allResources=\App\Admin\ResourceRegistry::all(); @endphp
                @foreach ($groups as $heading => $keys)
                    <h2>{{ $heading }}</h2><ul>@foreach ($keys as $key) @isset($allResources[$key]) @can('viewAny', $allResources[$key]->model())<li><a href="{{ route('admin.'.$key.'.index') }}" @if(request()->routeIs('admin.'.$key.'.*')) aria-current="page" @endif>{{ $allResources[$key]->pluralLabel() }}</a></li>@endcan @endisset @endforeach</ul>
                @endforeach
                @can('viewAny', \App\Models\User::class)<ul><li><a href="{{ route('admin.user.index') }}">Benutzerkonten</a></li></ul>@endcan
            </nav>
        </details>
    </aside>
    <div class="cms-content">
        <header class="cms-topbar"><p><a href="{{ route('public.home') }}">Website ansehen ↗</a></p><nav aria-label="Konto"><ul class="nav-list"><li>{{ auth()->user()->name }}</li><li><a href="{{ route('admin.two-factor.setup') }}">Konto und Sicherheit</a></li><li><form method="POST" action="{{ route('admin.logout') }}">@csrf<button type="submit" class="button button--secondary">Abmelden</button></form></li></ul></nav></header>
        <main id="inhalt" class="cms-main" tabindex="-1">@yield('content')</main>
    </div>
</div>
@else
    <header class="auth-header">{{ config('app.name') }} · Redaktion</header>
    <main id="inhalt" class="auth-main" tabindex="-1">@yield('content')</main>
@endauth
@endsection
