@if ($records->isEmpty())<p>Zurzeit sind keine Haushaltspläne veröffentlicht.</p>@else
<ul class="plain-list budget-list">
    @foreach ($records as $plan)
        @php $receipt = $plan->publications->first(); $pdf = $receipt->generation; @endphp
        <li class="content-section"><h2><a href="{{ \App\Support\Routing\PublicPath::toUrl($plan->publicPath()) }}">{{ $plan->title }}</a></h2>
            @if ($plan->description)<p>{{ $plan->description }}</p>@endif
            <p class="meta">{{ $receipt->topic ?? $plan->topic }}</p>
            @if ($receipt->source_only)
                <p><a class="button" href="{{ \App\Support\Routing\PublicPath::toUrl($plan->publicPath()) }}">Haushaltsplan und Anlagen ansehen</a></p>
                <p class="meta">{{ count($receipt->source_manifest) }} Originaldateien · {{ $receipt->accessibility_status->label() }}</p>
            @else
            <p><a class="button" href="{{ route('public.budget.package.download', [$plan->year, $plan->id]) }}">{{ $receipt->title }} herunterladen (PDF)</a></p>
            <p class="meta">{{ $pdf->page_count }} Seiten · {{ number_format($pdf->size_bytes / 1024 / 1024, 1, ',', '.') }} MB · {{ $receipt->accessibility_status->label() }}</p>
            @endif
        </li>
    @endforeach
</ul>
{{ $records->links('public.partials.pagination') }}
@endif
