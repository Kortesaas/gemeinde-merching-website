@extends('layouts.admin')

@section('title', 'Neues Passwort festlegen')

@section('content')
    <h1>Neues Passwort festlegen</h1>

    <x-form.error-summary />

    <form method="POST" action="{{ route('admin.password.update') }}" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-form.field
            name="email"
            label="E-Mail-Adresse"
            type="email"
            :value="old('email', $email)"
            autocomplete="username"
            autocapitalize="none"
            spellcheck="false"
            required
        />
        <x-form.field
            name="password"
            label="Neues Passwort"
            type="password"
            hint="Mindestens 12 Zeichen. Ein langer Satz aus mehreren Wörtern ist sicher und gut zu merken."
            autocomplete="new-password"
            required
        />
        <x-form.field
            name="password_confirmation"
            label="Neues Passwort wiederholen"
            type="password"
            autocomplete="new-password"
            required
        />
        <button type="submit" class="button">Passwort speichern</button>
    </form>
@endsection
