@extends('layouts.admin')

@section('title', 'Benutzerkonten')

@section('content')
    <header class="cms-page-header">
        <div><p class="cms-eyebrow"><a href="{{ route('admin.dashboard') }}">Dashboard</a></p><h1>Benutzerkonten</h1></div>
        @can('create', \App\Models\User::class)<div class="cms-page-actions"><a class="button" href="{{ route('admin.user.create') }}"><x-icon name="plus" /> Konto anlegen</a></div>@endcan
    </header>
    <x-status />
    <div class="table-wrapper" role="region" aria-label="Datentabelle, horizontal verschiebbar" tabindex="0">
        <table class="data-table data-table--stack" role="table">
            <caption class="visually-hidden">Benutzerkonten</caption>
            <thead><tr role="row"><th scope="col" role="columnheader">Name</th><th scope="col" role="columnheader">E-Mail</th><th scope="col" role="columnheader">Rollen</th><th scope="col" role="columnheader">Status</th><th scope="col" role="columnheader">Zwei-Faktor</th></tr></thead>
            <tbody>
                @foreach ($users as $account)
                    <tr role="row">
                        <th scope="row" role="rowheader">@can('update', $account)<a href="{{ route('admin.user.edit', $account) }}">{{ $account->name }}</a>@else{{ $account->name }}@endcan</th>
                        <td role="cell" data-label="E-Mail">{{ $account->email }}</td>
                        <td role="cell" data-label="Rollen">{{ $account->roles->map(fn ($r) => \App\Support\Authorization\Role::tryFrom($r->name)?->label() ?? $r->name)->implode(', ') ?: '–' }}</td>
                        <td role="cell" data-label="Status"><span class="state {{ $account->is_active ? 'state--active' : 'state--inactive' }}">{{ $account->is_active ? 'Aktiv' : 'Deaktiviert' }}</span></td>
                        <td role="cell" data-label="Zwei-Faktor">@if ($account->hasEnabledTwoFactor())<span class="severity severity--ok"><x-icon name="check" />eingerichtet</span>@else<span class="severity severity--warning"><x-icon name="warning" />fehlt</span>@endif</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    {{ $users->links('admin.partials.pagination') }}
@endsection
