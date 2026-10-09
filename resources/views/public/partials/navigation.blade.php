@php $trailIds = collect($trail ?? [])->map(fn ($node) => $node->item->getKey())->all(); @endphp
<ul class="main-nav__list">
@foreach ($nodes as $node)
    @php $href = $node->href; $current = in_array($node->item->getKey(), $trailIds, true); $self = request()->getPathInfo() === $href; @endphp
    <li class="main-nav__item {{ $node->children ? 'main-nav__item--branch' : '' }}" @if ($node->children) data-nav-item @endif>
        {{-- The label always leads to the section's overview page. --}}
        <a class="main-nav__link {{ $current ? 'is-current' : '' }}" href="{{ $href }}" @if ($self) aria-current="page" @endif>{{ $node->item->label }}@if ($current && ! $self)<span class="visually-hidden"> (aktueller Bereich)</span>@endif</a>
        @if ($node->children)
            {{-- Separate toggle: keyboard and touch open the section without leaving the page. --}}
            <details class="nav-branch" data-nav-branch>
                <summary class="nav-branch__toggle"><span class="visually-hidden">Untermenü {{ $node->item->label }}</span><x-icon name="chevron-down" class="nav-branch__chevron" /><x-icon name="plus" class="nav-branch__plus" /><x-icon name="minus" class="nav-branch__minus" /></summary>
                <div class="mega">
                    <div class="container mega__inner">
                        <div class="mega__intro">
                            <p class="mega__title">{{ $node->item->label }}</p>
                            @php $summary = $node->target?->getAttribute('summary'); @endphp
                            @if ($summary)<p class="mega__summary">{{ $summary }}</p>@endif
                            <a class="mega__overview" href="{{ $href }}" @if ($self) aria-current="page" @endif>Übersicht<span class="visually-hidden"> {{ $node->item->label }}</span><x-icon name="arrow-right" /></a>
                        </div>
                        <ul class="mega__links">
                            @foreach ($node->children as $child)
                                @php $childHref = $child->href; @endphp
                                <li>
                                    <a href="{{ $childHref }}" @if (request()->getPathInfo() === $childHref) aria-current="page" @endif>{{ $child->item->label }}@if ($child->isExternal())<span class="visually-hidden"> (externer Link)</span><x-icon name="external" class="icon--inline" />@endif</a>
                                    @if ($child->children)
                                        <ul>
                                            @foreach ($child->children as $grandchild)
                                                <li><a href="{{ $grandchild->href }}">{{ $grandchild->item->label }}</a></li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </details>
        @endif
    </li>
@endforeach
</ul>
