{{-- Placed documents grouped by slot and optional group label. --}}
@php
    $slots = method_exists($owner, 'documentSlots') ? $owner::documentSlots() : [];
    $groups = $documents->groupBy(fn ($d) => $d->pivot?->slot ?? '');
@endphp
@foreach ($groups as $slot => $items)
    <section class="content-section" aria-labelledby="downloads-{{ $loop->index }}">
        <h2 id="downloads-{{ $loop->index }}">{{ $headings[$slot] ?? $slots[$slot] ?? 'Dokumente' }}</h2>
        @foreach ($items->groupBy(fn ($d) => $d->pivot?->group_label ?? '') as $label => $grouped)
            @if ($label !== '')<h3 class="group-label">{{ $label }}</h3>@endif
            <ul class="download-list">
                @foreach ($grouped as $document)<li>@include('public.partials.download-item', ['document' => $document])</li>@endforeach
            </ul>
        @endforeach
    </section>
@endforeach
