@if ($model->exists && \App\Models\BudgetSource::query()->where('budget_plan_id', $model->getKey())->exists())
    @include('admin.fields.rows')
@else
    <p class="form-hint" id="components">{{ $model->exists ? 'Noch keine PDF-Dateien. Oben mehrere Dateien auswählen und hochladen.' : 'Die PDF-Dateien können Sie nach dem Anlegen des Entwurfs hinzufügen.' }}</p>
@endif
