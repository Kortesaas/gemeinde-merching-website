@extends('layouts.admin')

@section('title', 'Anmeldung bestätigen')

@section('content')
    <div class="auth-step auth-challenge">
        <h1>Anmeldung bestätigen</h1>
        <p class="auth-challenge__intro">Code aus Ihrer Authenticator-App eingeben.</p>

        <x-status />
        <x-form.error-summary />

        <form class="auth-challenge__code" method="POST" action="{{ route('admin.two-factor.challenge.store') }}" novalidate>
            @csrf
            <x-form.field
                name="code"
                label="6-stelliger Bestätigungscode"
                inputmode="numeric"
                autocomplete="one-time-code"
                spellcheck="false"
                required
            />
            <button type="submit" class="button">Anmelden</button>
        </form>

        <details class="auth-recovery" @if ($errors->has('recovery_code') || old('recovery_code')) open @endif>
            <summary>Wiederherstellungscode verwenden <x-icon name="chevron-down" /></summary>
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
        </details>
        <p class="auth-challenge__back"><a href="{{ route('admin.login') }}">Zurück zur Anmeldung</a></p>
    </div>
@endsection
