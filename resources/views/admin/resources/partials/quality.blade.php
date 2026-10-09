@php $issues=app(\App\Services\Quality\QualityChecks::class)->inspect($model); $blocking=collect($issues)->contains(fn($i)=>$i->severity === \App\Enums\QualitySeverity::Error); @endphp
<section class="cms-panel quality-panel {{ $blocking ? 'quality-error' : '' }}" aria-labelledby="quality-heading" id="quality">
    <h2 id="quality-heading">Qualität vor Veröffentlichung</h2>
    <p>{{ $blocking ? 'Fehler blockieren die Veröffentlichung. Bitte beheben Sie die folgenden Punkte.' : 'Keine blockierenden Fehler. Warnungen bitte prüfen; Empfehlungen sind optional.' }}</p>
    @if ($issues !== [])<ul>@foreach($issues as $issue)<li><span class="quality-label">{{ $issue->severity->label() }}:</span> {{ $issue->message }} @if($issue->field)<a href="#{{ $issue->field }}">Feld prüfen</a>@endif</li>@endforeach</ul>@else<p>Die automatischen Prüfungen haben keine Hinweise ergeben.</p>@endif
    <p class="form-hint">Automatische Hinweise ersetzen keine manuelle Prüfung von Inhalt und Barrierefreiheit.</p>
</section>
