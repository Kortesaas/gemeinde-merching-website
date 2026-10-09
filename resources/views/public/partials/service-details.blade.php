@foreach (['prerequisites' => 'Voraussetzungen', 'required_items' => 'Benötigte Unterlagen / Gegenstände', 'processing_duration' => 'Bearbeitungsdauer', 'important_notice' => 'Wichtiger Hinweis'] as $key => $label)
    @if ($model->getAttribute($key))<h2>{{ $label }}</h2><p>{{ $model->getAttribute($key) }}</p>@endif
@endforeach
@php $fees=$model->fees()->get(); $hasContext=$fees->contains(fn($fee)=>trim((string)$fee->context)!==''); $hasNote=$fees->contains(fn($fee)=>trim((string)$fee->note)!==''); @endphp
@if ($fees->isNotEmpty())
    <div class="table-wrapper" role="region" aria-label="Gebühren" tabindex="0"><table class="fee-table">
        <caption>Gebühren</caption>
        <thead><tr><th scope="col">Beschreibung</th>@if($hasContext)<th scope="col">Gültigkeit / Kontext</th>@endif<th scope="col">Betrag</th>@if($hasNote)<th scope="col">Hinweis</th>@endif</tr></thead>
        <tbody>@foreach ($fees as $fee)<tr><th scope="row">{{ $fee->description }}</th>@if($hasContext)<td>{{ $fee->context }}</td>@endif<td class="fee-amount">{{ $fee->amount !== null ? number_format((float) $fee->amount, 2, ',', '.').' EUR' : 'Nicht angegeben' }}</td>@if($hasNote)<td>{{ $fee->note }}</td>@endif</tr>@endforeach</tbody>
    </table></div>
@endif
