@extends('layouts.public', ['robots'=>'noindex, nofollow'])
@section('title','Kontakt')
@section('canonical', \App\Support\Routing\PublicPath::absoluteUrl('/kontakt'))
@section('content')
<h1>Kontakt</h1>
<p>Ihre Angaben werden zur Bearbeitung Ihrer Anfrage per E-Mail an die zuständige Stelle übermittelt. Bitte nennen Sie nur die dafür erforderlichen Informationen.</p>
<x-form.error-summary />
<form id="general" tabindex="-1" method="POST" action="{{ route('public.contact.store') }}" novalidate>
    @csrf
    <input type="hidden" name="form_nonce" value="{{ $nonce }}">
    @php $hasError=$errors->has('contact_route_id'); @endphp
    <div class="form-field">
        <label class="form-label" for="contact_route_id">Thema (Pflichtfeld)</label>
        @if($hasError)<p class="form-error" id="contact_route_id-error">Fehler: {{ $errors->first('contact_route_id') }}</p>@endif
        <select class="form-input" id="contact_route_id" name="contact_route_id" required @if($hasError) aria-invalid="true" aria-describedby="contact_route_id-error" @endif>
            <option value="">Bitte wählen</option>
            @foreach($topics as $topic)<option value="{{ $topic['id'] }}">{{ $topic['label'] }}</option>@endforeach
        </select>
    </div>
    <x-form.field name="contact_name" label="Name (Pflichtfeld)" required maxlength="150" autocomplete="name" />
    <x-form.field name="contact_email" type="email" label="E-Mail (Pflichtfeld)" required maxlength="254" autocomplete="email" />
    <x-form.field name="contact_phone" type="tel" label="Telefonnummer (optional)" maxlength="50" autocomplete="tel" />
    @php $hasError=$errors->has('contact_message'); @endphp
    <div class="form-field">
        <label class="form-label" for="contact_message">Nachricht (Pflichtfeld)</label>
        @if($hasError)<p class="form-error" id="contact_message-error">Fehler: {{ $errors->first('contact_message') }}</p>@endif
        <textarea class="form-input" id="contact_message" name="contact_message" rows="8" required minlength="10" maxlength="10000" @if($hasError) aria-invalid="true" aria-describedby="contact_message-error" @endif></textarea>
    </div>
    <div hidden aria-hidden="true"><label for="website">Dieses Feld bitte leer lassen</label><input id="website" name="website" tabindex="-1" autocomplete="off"></div>
    <p>Dieses Formular verwendet einen notwendigen Sitzungscookie für den Schutz der Übermittlung. Die Nachricht wird nicht als Datenbankeintrag gespeichert.</p>
    <button class="button" type="submit">Nachricht senden</button>
</form>
@endsection
