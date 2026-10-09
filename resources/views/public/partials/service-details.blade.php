@foreach (['prerequisites' => 'Voraussetzungen', 'required_items' => 'Benötigte Unterlagen / Gegenstände', 'processing_duration' => 'Bearbeitungsdauer', 'important_notice' => 'Wichtiger Hinweis'] as $key => $label)
    @if ($model->getAttribute($key))<h2>{{ $label }}</h2><p>{{ $model->getAttribute($key) }}</p>@endif
@endforeach
@if ($model->fees()->exists())
    <table>
        <caption>Gebühren</caption>
        <thead><tr><th scope="col">Beschreibung</th><th scope="col">Gültigkeit / Kontext</th><th scope="col">Betrag</th><th scope="col">Hinweis</th></tr></thead>
        <tbody>@foreach ($model->fees()->get() as $fee)<tr><th scope="row">{{ $fee->description }}</th><td>{{ $fee->context }}</td><td>{{ $fee->amount !== null ? number_format((float) $fee->amount, 2, ',', '.').' EUR' : 'Nicht angegeben' }}</td><td>{{ $fee->note }}</td></tr>@endforeach</tbody>
    </table>
@endif
@if ($model->online_service_mode !== \App\Enums\OnlineServiceMode::NotSpecified)
    <p>{{ $model->online_service_mode->label() }}</p>
    @if ($model->online_service_mode !== \App\Enums\OnlineServiceMode::Unavailable && $model->onlineService && $model->onlineService->isPubliclyReachable())
        <p><a href="{{ $model->onlineService->url }}">{{ $model->onlineService->title }}</a>@if ($model->onlineService->privacy_note) – {{ $model->onlineService->privacy_note }}@endif</p>
    @endif
@endif
