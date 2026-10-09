@extends('layouts.admin')

@section('title', 'Benutzerkonten')

@section('content')
    <header class="cms-page-header">
        <div><p class="cms-eyebrow"><a href="{{ route('admin.dashboard') }}">Dashboard</a></p><h1>Benutzerkonten</h1></div>
        @can('create', \App\Models\User::class)<div class="cms-page-actions"><a class="button" href="{{ route('admin.user.create') }}"><x-icon name="plus" /> Konto anlegen</a></div>@endcan
    </header>
    <x-status />
    <div class="table-wrapper" role="region" aria-label="Datentabelle, horizontal verschiebbar" tabindex="0">
        <table class="data-table">
            <caption class="visually-hidden">Benutzerkonten</caption>
            <thead><tr><th scope="col">Name</th><th scope="col">E-Mail</th><th scope="col">Rollen</th><th scope="col">Status</th><th scope="col">Zwei-Faktor</th></tr></thead>
            <tbody>
                @foreach ($users as $account)
                    <tr>
                        <th scope="row">@can('update', $account)<a href="{{ route('admin.user.edit', $account) }}">{{ $account->name }}</a>@else{{ $account->name }}@endcan</th>
                        <td>{{ $account->email }}</td>
                        <td>{{ $account->roles->map(fn ($r) => \App\Support\Authorization\Role::tryFrom($r->name)?->label() ?? $r->name)->implode(', ') ?: '–' }}</td>
                        <td><span class="state {{ $account->is_active ? 'state--active' : 'state--inactive' }}">{{ $account->is_active ? 'Aktiv' : 'Deaktiviert' }}</span></td>
                        <td>@if ($account->hasEnabledTwoFactor())<span class="severity severity--ok"><x-icon name="check" />eingerichtet</span>@else<span class="severity severity--warning"><x-icon name="warning" />fehlt</span>@endif</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    {{ $users->links('admin.partials.pagination') }}
@endsection
