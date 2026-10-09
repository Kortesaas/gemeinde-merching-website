@extends('layouts.public', ['robots'=>'noindex, nofollow'])
@section('title','Kontakt – Bestätigung')
@section('content')
<div class="confirmation">
    <x-icon name="check" class="confirmation__icon" />
    <h1>Vielen Dank für Ihre Nachricht</h1>
    <p class="lead">Ihre Anfrage wurde übermittelt. Die zuständige Stelle meldet sich bei Bedarf bei Ihnen.</p>
    <p><a class="button button--pill" href="{{ route('public.home') }}">Zur Startseite</a></p>
</div>
@endsection
