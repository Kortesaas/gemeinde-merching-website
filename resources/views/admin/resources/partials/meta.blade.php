<dl class="summary-list summary-list--inline">
    <dt>Status</dt>
    <dd>@include('admin.resources.partials.state', ['model' => $model])</dd>
    @if ($resource->isRoutable() && $model->publicPath())
        <dt>Öffentliche Adresse</dt>
        <dd><code>{{ $model->publicPath() }}</code></dd>
    @endif
    <dt>Zuletzt geändert</dt>
    <dd>{{ \App\Support\SiteTime::format($model->updated_at) }}@if (($model->getAttributes()['updated_by'] ?? null) !== null) durch {{ $model->editor?->name }}@endif</dd>
    @if ($resource->hasRevisions())
        <dt>Versionen</dt>
        <dd><a href="{{ route('admin.'.$resource->key().'.revisions', $model->getKey()) }}">Versionsgeschichte anzeigen</a></dd>
    @endif
</dl>
