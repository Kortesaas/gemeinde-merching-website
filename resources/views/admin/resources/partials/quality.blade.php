@php
    $issues = app(\App\Services\Quality\QualityChecks::class)->inspect($model);
    $blocking = collect($issues)->contains(fn ($i) => $i->severity === \App\Enums\QualitySeverity::Error);
@endphp
<section class="editor-card quality-panel {{ $blocking ? 'quality-panel--error' : '' }}" aria-labelledby="quality-heading" id="quality">
    <h2 class="editor-card__title" id="quality-heading">Qualität</h2>
    <div class="editor-card__body">
        <p class="quality-summary">
            @if ($blocking)<x-icon name="error" /> Fehler blockieren die Veröffentlichung.
            @elseif ($issues !== [])<x-icon name="info" /> Keine blockierenden Fehler.
            @else<x-icon name="check" /> Keine Hinweise der automatischen Prüfung.@endif
        </p>
        @if ($issues !== [])
            <ul class="quality-list">
                @foreach ($issues as $issue)
                    <li>
                        @include('admin.partials.severity', ['severity' => $issue->severity])
                        <span>{{ $issue->message }} @if ($issue->field)<a href="#{{ $issue->field }}">Zum Feld</a>@endif</span>
                    </li>
                @endforeach
            </ul>
        @endif
        <p class="form-hint">Automatische Hinweise ersetzen keine manuelle Prüfung von Inhalt und Barrierefreiheit.</p>
    </div>
</section>
