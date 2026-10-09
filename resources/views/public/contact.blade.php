@extends('layouts.public', ['robots'=>'noindex, nofollow'])
@section('title','Kontakt')
@section('canonical', \App\Support\Routing\PublicPath::absoluteUrl('/kontakt'))
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
<x-form.error-summary />
<form class="public-form" data-contact-form id="general" tabindex="-1" method="POST" action="{{ route('public.contact.store', $context ? ['feedback' => $context['path']] : []) }}" novalidate>
    @csrf
    <input type="hidden" name="form_nonce" value="{{ $nonce }}">
    @php $hasError=$errors->has('contact_route_id'); @endphp
    <div class="form-field">
        <label class="form-label" for="contact_route_id">Thema (Pflichtfeld)</label>
        @if($hasError)<p class="form-error" id="contact_route_id-error">Fehler: {{ $errors->first('contact_route_id') }}</p>@endif
        <select class="form-input" id="contact_route_id" name="contact_route_id" required @if($hasError) aria-invalid="true" aria-describedby="contact_route_id-error" @endif>
            <option value="">Bitte wählen</option>
            @foreach($topics as $topic)<option value="{{ $topic['id'] }}" @selected((string) $selectedTopic === (string) $topic['id'])>{{ $topic['label'] }}</option>@endforeach
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
