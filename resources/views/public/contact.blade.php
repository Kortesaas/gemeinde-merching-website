@extends('layouts.public', ['robots'=>'noindex, nofollow'])
@section('title','Kontakt')
@section('canonical', \App\Support\Content\SeoUrl::path('/kontakt'))
@section('content')
@php
    $settings = app(\App\Services\Settings\SiteConfiguration::class)->current();
    $central = $settings?->centralDepartment?->isPubliclyReachable() ? $settings->centralDepartment : null;
    $townHall = $settings?->townHall?->isPubliclyReachable() ? $settings->townHall : null;
@endphp
<header class="page-header">
    <h1>{{ $context ? 'Fehler melden' : 'Kontakt' }}</h1>
    <p class="lead">{{ $context ? 'Haben Sie auf unserer Website einen Fehler oder veraltete Angaben entdeckt? Vielen Dank für Ihren Hinweis.' : 'Schreiben Sie uns – Ihre Nachricht wird an die zuständige Stelle im Rathaus weitergeleitet.' }}</p>
</header>
<div class="content-layout">
<div class="content-main">
@if ($context)<p class="notice-box"><x-icon name="info" /> <span>Fehler melden zu „<a href="{{ $context['path'] }}">{{ $context['title'] }}</a>“. Bitte beschreiben Sie den Fehler in Ihrer Nachricht.</span></p>@endif
<p>Ihre Angaben werden zur Bearbeitung Ihrer Anfrage per E-Mail an die zuständige Stelle übermittelt. Bitte nennen Sie nur die dafür erforderlichen Informationen.</p>
<p>Sie erhalten eine Eingangsbestätigung mit einer Kopie Ihrer Anfrage per E-Mail. @unless ($context)Für die Antwort auf Ihr Anliegen können Sie E-Mail oder Post wählen.@endunless</p>
<x-form.error-summary />
<form class="public-form" data-contact-form id="general" tabindex="-1" method="POST" action="{{ route('public.contact.store', $context ? ['feedback' => $context['path']] : []) }}" novalidate>
    @csrf
    <input type="hidden" name="form_nonce" value="{{ $nonce }}">
    @php $hasError=$errors->has('contact_route_id'); @endphp
    <div class="form-field">
        <label class="form-label" for="contact_route_id">Empfänger (Pflichtfeld)</label>
        @if($hasError)<p class="form-error" id="contact_route_id-error">Fehler: {{ $errors->first('contact_route_id') }}</p>@endif
        <select class="form-input" id="contact_route_id" name="contact_route_id" required aria-describedby="contact-route-hint{{ $hasError ? ' contact_route_id-error' : '' }}" @if($hasError) aria-invalid="true" @endif>
            <option value="">Bitte wählen</option>
            @foreach($topics as $topic)<option value="{{ $topic['id'] }}" @selected((string) $selectedTopic === (string) $topic['id'])>{{ $topic['label'] }}</option>@endforeach
        </select>
        <p class="form-hint contact-recipient" id="contact-route-hint" data-contact-recipient aria-live="polite" hidden></p>
    </div>
    <x-form.field name="contact_name" label="Name (Pflichtfeld)" required maxlength="150" autocomplete="name" />
    <x-form.field name="contact_email" type="email" label="E-Mail (Pflichtfeld)" required maxlength="254" autocomplete="email" />
    <x-form.field name="contact_phone" type="tel" label="Telefonnummer (optional)" maxlength="50" autocomplete="tel" />
    @unless ($context)
        <fieldset class="contact-group">
            <legend>Postanschrift</legend>
            <x-form.field name="contact_street" label="Straße und Hausnummer (Pflichtfeld)" required maxlength="150" autocomplete="street-address" />
            <div class="contact-address">
                <x-form.field name="contact_postal_code" label="PLZ (Pflichtfeld)" required maxlength="20" autocomplete="postal-code" />
                <x-form.field name="contact_city" label="Ort (Pflichtfeld)" required maxlength="150" autocomplete="address-level2" />
            </div>
        </fieldset>
        <x-form.field name="contact_subject" label="Betreff (Pflichtfeld)" required maxlength="200" />
    @endunless
    @php $hasError=$errors->has('contact_message'); @endphp
    <div class="form-field">
        <label class="form-label" for="contact_message">Nachricht ({{ $context ? 'Pflichtfeld' : 'optional' }})</label>
        @if($hasError)<p class="form-error" id="contact_message-error">Fehler: {{ $errors->first('contact_message') }}</p>@endif
        <textarea class="form-input" id="contact_message" name="contact_message" rows="8" @if($context) required minlength="10" @endif maxlength="10000" @if($hasError) aria-invalid="true" aria-describedby="contact_message-error" @endif></textarea>
    </div>
    @unless ($context)
        @php $hasError=$errors->has('contact_reply_by'); @endphp
        <fieldset class="contact-group" @if($hasError) aria-describedby="contact_reply_by-error" @endif>
            <legend>Wie sollen wir antworten? (Pflichtfeld)</legend>
            @if($hasError)<p class="form-error" id="contact_reply_by-error">Fehler: {{ $errors->first('contact_reply_by') }}</p>@endif
            <label class="contact-choice"><input id="contact_reply_by" type="radio" name="contact_reply_by" value="email" required @checked(old('contact_reply_by', 'email') === 'email') @if($hasError) aria-invalid="true" @endif> Mittels unverschlüsselter E-Mail</label>
            <label class="contact-choice"><input type="radio" name="contact_reply_by" value="post" required @checked(old('contact_reply_by') === 'post') @if($hasError) aria-invalid="true" @endif> Auf dem Postweg</label>
        </fieldset>
    @endunless
    @php $hasError=$errors->has('contact_privacy'); @endphp
    <div class="form-field">
        @if($hasError)<p class="form-error" id="contact_privacy-error">Fehler: {{ $errors->first('contact_privacy') }}</p>@endif
        <label class="contact-choice"><input id="contact_privacy" type="checkbox" name="contact_privacy" value="1" required @checked(old('contact_privacy')) @if($hasError) aria-invalid="true" aria-describedby="contact_privacy-error" @endif><span>Ich habe die <a href="/datenschutz">Datenschutzerklärung</a> gelesen und stimme der Übermittlung meiner Angaben zu. (Pflichtfeld)</span></label>
    </div>
    <div hidden aria-hidden="true"><label for="website">Dieses Feld bitte leer lassen</label><input id="website" name="website" tabindex="-1" autocomplete="off"></div>
    <p class="form-hint">Dieses Formular verwendet einen notwendigen Sitzungscookie für den Schutz der Übermittlung. Die Nachricht wird nicht als Datenbankeintrag gespeichert.</p>
    <button class="button button--pill" type="submit">Nachricht senden</button>
</form>
</div>
@if ($central || $townHall)
    <aside class="content-aside" aria-labelledby="contact-aside">
        <h2 id="contact-aside" class="aside-heading">Lieber persönlich oder telefonisch?</h2>
        @if ($central)<p class="contact-card__name">{{ $central->name }}</p>@include('public.partials.contact-data', ['contact' => $central])@endif
        @if ($townHall)<div class="aside-section">@include('public.partials.location', ['location' => $townHall])</div>@endif
    </aside>
@endif
</div>
@endsection
