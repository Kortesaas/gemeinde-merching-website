<section class="editor-card" aria-labelledby="pdf-package-heading">
    <h2 class="editor-card__title" id="pdf-package-heading">Gesamt-PDF vorbereiten</h2>
    <div class="editor-card__body budget-package">
    @if ($isNew)
        <p>Zuerst Haushaltsjahr und Titel eintragen und den Entwurf anlegen. Danach können Sie mehrere PDF-Dateien gemeinsam hochladen.</p>
    @else
        @php $generation = $model->currentGeneration()->first(); $receipts = $model->publications()->get(); @endphp
        <p><strong>{{ ['ready' => 'Gesamt-PDF erstellt', 'stale' => 'Gesamt-PDF muss erstellt werden', 'failed' => 'Zusammenstellung fehlgeschlagen'][$model->generation_status] }}</strong></p>
        @if ($model->generation_error)<p class="form-error" role="alert">{{ $model->generation_error }}</p>@endif
        <p class="form-hint">PDF-Dateien hochladen → Reihenfolge speichern → Gesamt-PDF erstellen und prüfen → veröffentlichen.</p>
        <div class="cms-action-footer">
            @if ($editable)<button class="button button--secondary" type="submit" form="budget-generate">Gesamt-PDF erstellen</button>@endif
            @if ($generation)<a class="button button--secondary" href="{{ route('admin.budget-plan.file', [$model->id, 'gesamt', $generation->id]) }}">{{ $model->generation_status === 'ready' ? 'Gesamt-PDF prüfen' : 'Vorheriges Gesamt-PDF ansehen' }}</a>@endif
            @if ($receipts->isNotEmpty())<a class="button button--secondary" href="{{ route('admin.budget-plan.proof', [$model->id, $receipts->first()->id]) }}" target="_blank" rel="noopener">Upload-Nachweis drucken</a>@endif
        </div>
        <p class="form-hint" data-budget-unsaved role="status" hidden>Bitte Änderungen speichern, bevor Sie PDF-Dateien hochladen oder das Gesamt-PDF erstellen.</p>
        @if ($editable || $canPropose)
            <div class="form-field">
                <label class="form-label" for="files">PDF-Dateien hinzufügen</label>
                <input class="form-input" id="files" name="files[]" type="file" multiple accept="application/pdf,.pdf" form="budget-upload" aria-describedby="budget-upload-hint{{ $errors->has('files') || $errors->has('files.*') ? ' files-error' : '' }}" @if ($errors->has('files') || $errors->has('files.*')) aria-invalid="true" @endif>
                <p class="form-hint" id="budget-upload-hint">Mehrere Dateien auswählbar. Nur PDF, höchstens {{ config('budgets.max_sources') }} Dateien pro Upload und {{ (int) config('uploads.max_kilobytes') / 1024 }} MB je Datei; höchstens {{ (int) config('budgets.max_total_bytes') / 1024 / 1024 }} MB im Gesamtpaket. Originaldateien bleiben einzeln gespeichert.</p>
                @if ($errors->has('files') || $errors->has('files.*'))<p class="form-error" id="files-error" role="alert">{{ $errors->first('files') ?: $errors->first('files.*') }}</p>@endif
                <button class="button button--secondary" type="submit" form="budget-upload">PDF-Dateien hochladen</button>
            </div>
        @endif
        @if ($model->sources()->exists())
            <details><summary>Hochgeladene Originaldateien</summary><ul class="plain-list">
                @foreach ($model->sources()->get() as $source)<li><a href="{{ route('admin.budget-plan.file', [$model->id, 'quelle', $source->id]) }}">{{ $source->original_filename }}</a> <span class="cms-row__meta">Upload #{{ $source->id }} · {{ number_format($source->size_bytes / 1024, 0, ',', '.') }} KB</span></li>@endforeach
            </ul></details>
        @endif
        @if ($receipts->count() > 1)<details><summary>Frühere Veröffentlichungsnachweise</summary><ul>@foreach ($receipts->skip(1) as $receipt)<li><a href="{{ route('admin.budget-plan.proof', [$model->id, $receipt->id]) }}">{{ \App\Support\SiteTime::format($receipt->created_at) }} – {{ $receipt->title }}</a></li>@endforeach</ul></details>@endif
    @endif
    </div>
</section>
