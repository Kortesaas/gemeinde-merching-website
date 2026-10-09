@extends('layouts.public', ['robots'=>'noindex, nofollow'])
@section('title','Kontakt – Bestätigung')
@section('content')
<h1>Vielen Dank für Ihre Nachricht</h1>
<p>Ihre Anfrage wurde übermittelt.</p>
<p><a href="{{ route('public.home') }}">Zur Startseite</a></p>
@endsection
