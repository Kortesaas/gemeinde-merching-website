@include('public.partials.page-header', ['eyebrow' => 'Haushalt & Finanzen', 'lead' => $model->description])
@php $receipt = $model->publications()->with('generation')->first(); @endphp
@if ($receipt)
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
