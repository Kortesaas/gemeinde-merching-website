@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
    <h1>Dashboard</h1>

    <x-status />

    <p lang="en">Backend foundation operational</p>

    <dl class="summary-list">
        <dt>Angemeldet als</dt>
        <dd>{{ auth()->user()->name }}</dd>
        <dt>Rollen</dt>
        <dd>
            {{ auth()->user()->getRoleNames()->map(fn ($role) => \App\Support\Authorization\Role::tryFrom($role)?->label() ?? $role)->implode(', ') ?: 'keine' }}
        </dd>
        <dt>Zwei-Faktor-Authentisierung</dt>
        <dd>{{ auth()->user()->hasEnabledTwoFactor() ? 'aktiv' : 'nicht eingerichtet' }}</dd>
    </dl>

    <nav aria-labelledby="content-nav-heading">
        <h2 id="content-nav-heading">Inhalte verwalten</h2>
        <ul class="link-list">
            @foreach (\App\Admin\ResourceRegistry::all() as $key => $resource)
                @can('viewAny', $resource->model())
                    <li><a href="{{ route('admin.'.$key.'.index') }}">{{ $resource->pluralLabel() }}</a></li>
                @endcan
            @endforeach
            @can('viewAny', \App\Models\User::class)
                <li><a href="{{ route('admin.user.index') }}">Benutzerkonten</a></li>
            @endcan
        </ul>
    </nav>
@endsection
