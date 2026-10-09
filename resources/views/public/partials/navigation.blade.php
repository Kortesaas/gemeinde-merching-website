<ul class="nav-tree">
@foreach ($nodes as $node)
    <li>
    @if ($node->children)
        <details data-nav-branch>
            <summary>{{ $node->item->label }}</summary>
            <ul class="nav-tree"><li><a href="{{ $node->item->href() }}">{{ $node->item->label }} – Übersicht</a></li>
                @foreach ($node->children as $child)
                    <li>@include('public.partials.navigation', ['nodes' => [$child]])</li>
                @endforeach
            </ul>
        </details>
    @else
        <a href="{{ $node->item->href() }}" @if (request()->getPathInfo() === parse_url($node->item->href(), PHP_URL_PATH)) aria-current="page" @endif>{{ $node->item->label }}</a>
    @endif
    </li>
@endforeach
</ul>
