@extends('layouts.public', ['robots'=>'noindex, nofollow'])
@section('title','Kontakt – Bestätigung')
@section('content')
<div class="confirmation">
    <x-icon name="check" class="confirmation__icon" />
    <h1>Vielen Dank für Ihre Nachricht</h1>
    <p class="lead">Ihre Anfrage wurde übermittelt. Die zuständige Stelle meldet sich bei Bedarf bei Ihnen.</p>
    @if (session('contact_receipt_sent') === true)
        <p>Eine Eingangsbestätigung mit einer Kopie Ihrer Anfrage wurde an Ihre E-Mail-Adresse gesendet.</p>
    @elseif (session('contact_receipt_sent') === false)
        <p>Ihre Anfrage wurde an die Gemeinde übermittelt. Die zusätzliche Eingangsbestätigung per E-Mail konnte nicht gesendet werden. Sie müssen Ihre Anfrage nicht erneut senden.</p>
    @endif
    <p><a class="button button--pill" href="{{ route('public.home') }}">Zur Startseite</a></p>
</div>
@endsection
