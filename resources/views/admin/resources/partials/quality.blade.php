@php $issues=app(\App\Services\Quality\QualityChecks::class)->inspect($model); @endphp
@if ($issues!==[])
<section aria-labelledby="quality-heading">
    <h2 id="quality-heading">Redaktionelle Qualitätsprüfung</h2>
    <ul>@foreach($issues as $issue)<li>{{ $issue->severity->label() }}: {{ $issue->message }}</li>@endforeach</ul>
    <p>Automatische Hinweise ersetzen keine manuelle Prüfung der Barrierefreiheit.</p>
</section>
@endif
