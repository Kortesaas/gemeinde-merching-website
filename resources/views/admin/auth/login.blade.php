@extends('layouts.admin')

@section('title', 'Anmelden')

@section('content')
    <h1>Anmelden</h1>

    <x-status />
    <x-form.error-summary />

    <form method="POST" action="{{ route('admin.login.store') }}" novalidate>
        @csrf
        <x-form.field
            name="email"
            label="E-Mail-Adresse"
            type="email"
            :value="old('email')"
            autocomplete="username"
            autocapitalize="none"
            spellcheck="false"
            required
        />
        <x-form.field
            name="password"
            label="Passwort"
            type="password"
            autocomplete="current-password"
            required
        />
        <button type="submit" class="button">Anmelden</button>
    </form>

    <p><a href="{{ route('admin.password.request') }}">Passwort vergessen?</a></p>
@endsection
