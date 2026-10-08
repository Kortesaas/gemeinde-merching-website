@extends('layouts.admin')

@section('title', 'Zwei-Faktor-Authentisierung')

@section('content')
    @if ($enabled)
        <h1>Zwei-Faktor-Authentisierung</h1>

        <x-status />
        <x-form.error-summary />

        <p>Die Zwei-Faktor-Authentisierung ist für Ihr Konto <strong>aktiv</strong>.</p>
        <p>Unbenutzte Wiederherstellungscodes: <strong>{{ $remainingRecoveryCodes }}</strong></p>

        <h2>Wiederherstellungscodes neu erzeugen</h2>
        <p>Alle bisherigen Wiederherstellungscodes werden dabei ungültig.</p>
        <form method="POST" action="{{ route('admin.two-factor.recovery-codes') }}" novalidate>
            @csrf
            <x-form.field
                name="current_password"
                label="Aktuelles Passwort"
                type="password"
                autocomplete="current-password"
                required
            />
            <button type="submit" class="button button--secondary">Neue Codes erzeugen</button>
        </form>
    @else
        <h1>Zwei-Faktor-Authentisierung einrichten</h1>

        <x-status />
        <x-form.error-summary />

        <p>Zum Schutz des Verwaltungsbereichs ist neben dem Passwort ein zweiter Faktor erforderlich.</p>

        <ol class="steps">
            <li>Installieren Sie eine Authenticator-App (TOTP) auf Ihrem Dienstgerät.</li>
            <li>
                <p>Scannen Sie den QR-Code mit der App oder geben Sie den Einrichtungsschlüssel manuell ein.</p>
                <img class="qr-code" src="{{ $qrCode }}" width="220" height="220"
                     alt="QR-Code zur Einrichtung der Authenticator-App. Alternativ den unten angezeigten Einrichtungsschlüssel eingeben.">
                <p>Einrichtungsschlüssel: <code class="secret">{{ $secret }}</code></p>
            </li>
            <li>Geben Sie zur Bestätigung den sechsstelligen Code ein, den die App anzeigt.</li>
        </ol>

        <form method="POST" action="{{ route('admin.two-factor.confirm') }}" novalidate>
            @csrf
            <x-form.field
                name="code"
                label="Sechsstelliger Code aus Ihrer Authenticator-App"
                inputmode="numeric"
                autocomplete="one-time-code"
                spellcheck="false"
                required
            />
            <button type="submit" class="button">Einrichtung bestätigen</button>
        </form>
    @endif
@endsection
