@extends('layouts.admin')

@section('title', 'Benutzerkonten')

@section('content')
    <p><a href="{{ route('admin.dashboard') }}">Zur Übersicht</a></p>
    <h1>Benutzerkonten</h1>
    <x-status />
    @can('create', \App\Models\User::class)
        <p><a class="button" href="{{ route('admin.user.create') }}">Konto anlegen</a></p>
    @endcan
    <div class="table-wrapper" role="region" aria-label="Datentabelle, horizontal verschiebbar" tabindex="0">
        <table class="data-table">
            <caption class="visually-hidden">Benutzerkonten</caption>
            <thead><tr><th scope="col">Name</th><th scope="col">E-Mail</th><th scope="col">Rollen</th><th scope="col">Status</th><th scope="col">Zwei-Faktor</th></tr></thead>
            <tbody>
                @foreach ($users as $account)
                    <tr>
                        <td>@can('update', $account)<a href="{{ route('admin.user.edit', $account) }}">{{ $account->name }}</a>@else{{ $account->name }}@endcan</td>
                        <td>{{ $account->email }}</td>
                        <td>{{ $account->roles->map(fn ($r) => \App\Support\Authorization\Role::tryFrom($r->name)?->label() ?? $r->name)->implode(', ') ?: '–' }}</td>
                        <td>{{ $account->is_active ? 'Aktiv' : 'Deaktiviert' }}</td>
                        <td>{{ $account->hasEnabledTwoFactor() ? 'eingerichtet' : 'nicht eingerichtet' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    {{ $users->links('admin.partials.pagination') }}
@endsection
