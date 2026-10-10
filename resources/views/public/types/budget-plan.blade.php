@include('public.partials.page-header', ['eyebrow' => 'Haushalt & Finanzen', 'lead' => $model->description])
@php $receipt = $model->publications()->with('generation')->first(); @endphp
@if ($receipt && $receipt->source_only)
    <p class="meta">{{ $receipt->topic }} · {{ $receipt->year }} · {{ $receipt->accessibility_status->label() }}</p>
    <section class="content-section" aria-labelledby="budget-originals">
        <h2 id="budget-originals">Haushaltsplan und Anlagen herunterladen</h2>
        <p>Die einzelnen Originaldateien stehen vollständig zur Verfügung. Ein zusammengefasstes Gesamt-PDF ist derzeit nicht verfügbar.</p>
        <ol class="plain-list">
            @foreach ($receipt->source_manifest as $source)
                <li><a href="{{ route('public.budget.package.source', [$receipt->year, $model->id, $source['id']]) }}">{{ $source['original_filename'] }}</a> <span class="meta">{{ number_format($source['size_bytes'] / 1024, 0, ',', '.') }} KB · Barrierefreiheit nicht geprüft</span></li>
            @endforeach
        </ol>
    </section>
@elseif ($receipt)
    <p class="meta">{{ $receipt->topic ?? $model->topic }} · {{ $receipt->year }}</p>
    @php $pdf = $receipt->generation; @endphp
    <section class="content-section" aria-labelledby="budget-download">
        <h2 id="budget-download">Gesamt-PDF herunterladen</h2>
        <p><a class="button" href="{{ route('public.budget.package.download', [$model->year, $model->id]) }}">Gesamt-PDF herunterladen</a></p>
        <p class="meta">{{ $pdf->page_count }} Seiten · {{ number_format($pdf->size_bytes / 1024 / 1024, 1, ',', '.') }} MB · {{ $receipt->accessibility_status->label() }}</p>
        @if ($receipt->accessibility_notes)<p>{{ $receipt->accessibility_notes }}</p>@endif
        @if ($receipt->show_components)
            <details class="accordion"><summary>Einzelne PDF-Dateien</summary><div class="accordion__body"><ol>
                @foreach ($pdf->sources as $source)<li><a href="{{ route('public.budget.package.source', [$model->year, $model->id, $source['id']]) }}">{{ $source['original_filename'] }}</a> <span class="meta">{{ $source['page_count'] }} Seiten · {{ number_format($source['size_bytes'] / 1024, 0, ',', '.') }} KB</span></li>@endforeach
            </ol></div></details>
        @endif
    </section>
@endif
