@extends('layouts.admin')

@section('title', $user->exists ? $user->name.' – Benutzerkonten' : 'Konto anlegen')

@section('content')
    <p><a href="{{ route('admin.user.index') }}">Zurück zu den Benutzerkonten</a></p>
    <h1>{{ $user->exists ? $user->name : 'Konto anlegen' }}</h1>
    <x-status />
    <x-form.error-summary />

    <form method="POST" action="{{ $user->exists ? route('admin.user.update', $user) : route('admin.user.store') }}" novalidate>
        @csrf
        @if ($user->exists) @method('PUT') @endif
        <x-form.field name="name" label="Name (Pflichtfeld)" :value="old('name', $user->name)" required autocomplete="off" />
        <x-form.field name="email" label="E-Mail-Adresse (Pflichtfeld)" type="email" :value="old('email', $user->email)" required autocomplete="off"
            hint="{{ $user->exists ? '' : 'An diese Adresse wird ein Link zum Festlegen des Passworts gesendet.' }}" />

        @php $selected = old('roles', $user->exists ? $user->getRoleNames()->all() : []); @endphp
        <fieldset id="roles" @class(['form-field', 'form-fieldset', 'form-field--error' => $errors->has('roles')])>
            <legend class="form-label">Rollen</legend>
            @if ($errors->has('roles'))
                <p class="form-error"><span class="form-error__prefix">Fehler:</span> {{ $errors->first('roles') }}</p>
            @endif
            @foreach ($roles as $role)
                <div class="form-option">
                    <input class="form-checkbox" type="checkbox" id="role-{{ $role->value }}" name="roles[]" value="{{ $role->value }}" @checked(in_array($role->value, $selected, true))>
                    <label class="form-checkbox-label" for="role-{{ $role->value }}">{{ $role->label() }}</label>
                </div>
            @endforeach
        </fieldset>

        @if ($user->exists)
            <x-form.checkbox name="is_active" label="Konto aktiv" :checked="(bool) old('is_active', $user->is_active)"
                hint="Deaktivierte Konten können sich nicht anmelden; laufende Sitzungen werden beendet." />
        @endif

        <button type="submit" class="button">{{ $user->exists ? 'Speichern' : 'Anlegen' }}</button>
    </form>
@endsection
