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
@endsection
