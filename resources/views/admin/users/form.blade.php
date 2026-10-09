@extends('layouts.admin')

@section('title', $user->exists ? $user->name.' – Benutzerkonten' : 'Konto anlegen')

@section('content')
    <div class="editor-bar">
        <a class="editor-bar__back" href="{{ route('admin.user.index') }}"><x-icon name="arrow-left" /> Benutzerkonten</a>
        @if ($user->exists)<span class="state {{ $user->is_active ? 'state--active' : 'state--inactive' }}">{{ $user->is_active ? 'Aktiv' : 'Deaktiviert' }}</span>@endif
        <div class="editor-bar__actions"><button type="submit" form="user-form" class="button">{{ $user->exists ? 'Konto speichern' : 'Konto anlegen' }}</button></div>
    </div>
    <p class="cms-eyebrow">Benutzerkonto · {{ $user->exists ? 'Bearbeiten' : 'Neuer Zugang' }}</p>
    <h1 class="editor-title">{{ $user->exists ? $user->name : 'Konto anlegen' }}</h1>
    <p class="cms-form-key">Diese Angaben sind nur intern sichtbar. Pflichtfelder sind gekennzeichnet.</p>
    <x-status />
    <x-form.error-summary />

    <form id="user-form" class="cms-user-form" method="POST" action="{{ $user->exists ? route('admin.user.update', $user) : route('admin.user.store') }}" novalidate>
        @csrf
        @if ($user->exists) @method('PUT') @endif
        <section class="editor-card" aria-labelledby="account-data-heading">
            <h2 class="editor-card__title" id="account-data-heading">Name & Anmeldung</h2>
            <div class="field-grid">
                <x-form.field name="name" label="Name (Pflichtfeld)" :value="old('name', $user->name)" required autocomplete="off" />
                <x-form.field name="email" label="E-Mail-Adresse (Pflichtfeld)" type="email" :value="old('email', $user->email)" required autocomplete="off"
                    hint="{{ $user->exists ? '' : 'An diese Adresse wird ein Link zum Festlegen des Passworts gesendet.' }}" />
            </div>
        </section>

        @php
            $selected = old('roles', $user->exists ? $user->getRoleNames()->all() : []);
            $descriptions = [
                'administrator' => 'Alle Bereiche, einschließlich Kontenverwaltung und endgültigem Löschen.',
                'chefredaktion' => 'Inhalte bearbeiten, veröffentlichen und Änderungsvorschläge freigeben.',
                'fachbereichsredaktion' => 'Inhalte vorbereiten und Änderungen vorschlagen; keine eigene Veröffentlichung.',
                'veranstaltungsredaktion' => 'Veranstaltungen bearbeiten und veröffentlichen; zugehörige Daten vorbereiten.',
                'pruefer' => 'Inhalte lesen; keine Bearbeitung oder Veröffentlichung.',
            ];
        @endphp
        <section class="editor-card" aria-labelledby="access-heading">
            <h2 class="editor-card__title" id="access-heading">Zugriff & Rollen</h2>
            <div class="editor-card__body">
                <fieldset id="roles" @class(['form-field', 'form-fieldset', 'form-field--error' => $errors->has('roles')])>
                    <legend class="form-label">Rollen auswählen (optional)</legend>
                    <p class="form-hint">Mehrere Rollen sind möglich. Ohne Rolle hat das Konto keinen Zugang zur Verwaltung.</p>
                    @if ($errors->has('roles'))<p class="form-error"><span class="form-error__prefix">Fehler:</span> {{ $errors->first('roles') }}</p>@endif
                    <div class="option-list">
                        @foreach ($roles as $role)
                            <div class="form-option">
                                <input class="form-checkbox" type="checkbox" id="role-{{ $role->value }}" name="roles[]" value="{{ $role->value }}" @checked(in_array($role->value, $selected, true))>
                                <label class="form-checkbox-label" for="role-{{ $role->value }}"><span>{{ $role->label() }}<span class="cms-role-description">{{ $descriptions[$role->value] }}</span></span></label>
                            </div>
                        @endforeach
                    </div>
                </fieldset>
                @if ($user->exists)
                    <x-form.checkbox name="is_active" label="Konto aktiv" :checked="(bool) old('is_active', $user->is_active)"
                        hint="Deaktivierte Konten können sich nicht anmelden; laufende Sitzungen werden beendet." />
                    <p class="form-hint">Zwei-Faktor-Anmeldung: <strong>{{ $user->hasEnabledTwoFactor() ? 'Eingerichtet' : 'Noch nicht eingerichtet' }}</strong></p>
                @endif
            </div>
        </section>
        <div class="cms-action-footer">
            <button type="submit" class="button">{{ $user->exists ? 'Konto speichern' : 'Konto anlegen' }}</button>
            <a href="{{ route('admin.user.index') }}" class="button button--secondary">Zur Liste</a>
        </div>
    </form>
@endsection
