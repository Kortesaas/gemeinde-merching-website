@php
    $slots = method_exists($owner, 'resourceSlots') ? $owner::resourceSlots() : [];
    $groups = $links->groupBy(fn ($l) => $l->pivot?->slot ?? '');
@endphp
@foreach ($groups as $slot => $items)
    <section class="content-section" aria-labelledby="links-{{ $loop->index }}">
        <h2 id="links-{{ $loop->index }}">{{ $slots[$slot] ?? 'Weiterführende Links' }}</h2>
        <ul class="external-list">@foreach ($items as $resource)<li>@include('public.partials.external-link', ['resource' => $resource])</li>@endforeach</ul>
    </section>
@endforeach
