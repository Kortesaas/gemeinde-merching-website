@extends('layouts.admin')

@section('title', 'Passwort vergessen')

@section('content')
    <h1>Passwort vergessen</h1>

    <x-status />
    <x-form.error-summary />

    <p>Geben Sie die E-Mail-Adresse Ihres Kontos ein. Wenn ein aktives Konto existiert, erhalten Sie eine E-Mail mit einem Link zum Festlegen eines neuen Passworts.</p>

    <form method="POST" action="{{ route('admin.password.email') }}" novalidate>
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
        <button type="submit" class="button">Link anfordern</button>
    </form>

    <p><a href="{{ route('admin.login') }}">Zurück zur Anmeldung</a></p>
@endsection
