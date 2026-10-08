@extends('layouts.admin')

@section('title', 'Bestätigung mit zweitem Faktor')

@section('content')
    <h1>Bestätigung mit zweitem Faktor</h1>

    <x-status />
    <x-form.error-summary />

    <form method="POST" action="{{ route('admin.two-factor.challenge.store') }}" novalidate>
        @csrf
        <x-form.field
            name="code"
            label="Sechsstelliger Code aus Ihrer Authenticator-App"
            hint="Öffnen Sie die App auf Ihrem Gerät und geben Sie den aktuell angezeigten Code ein."
            inputmode="numeric"
            autocomplete="one-time-code"
            spellcheck="false"
            required
        />
        <button type="submit" class="button">Bestätigen</button>
    </form>

    <h2>Kein Zugriff auf die Authenticator-App?</h2>
    <form method="POST" action="{{ route('admin.two-factor.recovery.store') }}" novalidate>
        @csrf
        <x-form.field
            name="recovery_code"
            label="Wiederherstellungscode"
            hint="Jeder Wiederherstellungscode kann nur einmal verwendet werden."
            autocomplete="off"
            autocapitalize="characters"
            spellcheck="false"
            required
        />
        <button type="submit" class="button button--secondary">Mit Wiederherstellungscode anmelden</button>
    </form>

    <p><a href="{{ route('admin.login') }}">Abbrechen und zur Anmeldung zurückkehren</a></p>
@endsection
