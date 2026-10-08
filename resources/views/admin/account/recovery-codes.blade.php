@extends('layouts.admin')

@section('title', 'Wiederherstellungscodes')

@section('content')
    <h1>Ihre Wiederherstellungscodes</h1>

    @if ($justEnabled)
        <div class="notice" role="status">
            <p>Die Zwei-Faktor-Authentisierung ist jetzt aktiv.</p>
        </div>
    @endif

    <div class="notice notice--warning">
        <h2 class="notice__title">Wichtig: Codes jetzt sicher aufbewahren</h2>
        <p>Diese Codes werden nur ein einziges Mal angezeigt. Mit jedem Code können Sie sich einmal anmelden, falls Sie keinen Zugriff auf Ihre Authenticator-App haben. Bewahren Sie die Codes getrennt von Ihrem Gerät auf (z. B. ausgedruckt an einem sicheren Ort oder im Passwortmanager).</p>
    </div>

    <ul class="recovery-codes">
        @foreach ($codes as $code)
            <li><code>{{ $code }}</code></li>
        @endforeach
    </ul>

    <p><a class="button" href="{{ route('admin.dashboard') }}">Weiter zum Dashboard</a></p>
@endsection
