@php $trailIds = collect($trail ?? [])->map(fn ($node) => $node->item->getKey())->all(); @endphp
<ul class="main-nav__list">
@foreach ($nodes as $node)
    @php $href = $node->href; $current = in_array($node->item->getKey(), $trailIds, true); @endphp
    <li class="main-nav__item">
    @if ($node->children)
        <details class="nav-branch" data-nav-branch>
            <summary class="nav-branch__toggle {{ $current ? 'is-current' : '' }}"><span>{{ $node->item->label }}</span><x-icon name="plus" class="nav-branch__plus" /><x-icon name="minus" class="nav-branch__minus" /></summary>
            <div class="mega">
                <div class="container mega__inner">
                    <div class="mega__intro">
                        <p class="mega__title">{{ $node->item->label }}</p>
                        @php $summary = $node->target?->getAttribute('summary'); @endphp
                        @if ($summary)<p class="mega__summary">{{ $summary }}</p>@endif
                        <a class="mega__overview" href="{{ $href }}" @if (request()->getPathInfo() === $href) aria-current="page" @endif>Übersicht<span class="visually-hidden"> {{ $node->item->label }}</span><x-icon name="arrow-right" /></a>
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
    @else
        <a class="main-nav__link {{ $current ? 'is-current' : '' }}" href="{{ $href }}" @if (request()->getPathInfo() === $href) aria-current="page" @endif>{{ $node->item->label }}</a>
    @endif
    </li>
@endforeach
</ul>
