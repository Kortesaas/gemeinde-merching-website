{{-- "Wo wird dieser Eintrag verwendet?" --}}
@php $usages = app(\App\Services\Content\ContentUsage::class)->of($model); @endphp
<section aria-labelledby="usage-heading">
    <h2 id="usage-heading">Verwendung</h2>
    @if ($usages === [])
        <p>Dieser Eintrag wird derzeit nirgends verwendet.</p>
    @else
        <ul>
            @foreach ($usages as $usage)
                @php $ownerResource = \App\Admin\ResourceRegistry::forModel($usage['owner']); @endphp
                <li>
                    @if ($ownerResource)
                        {{ $ownerResource->label() }}:
                        <a href="{{ route('admin.'.$ownerResource->key().'.edit', $usage['owner']->getKey()) }}">{{ $usage['owner']->displayTitle() }}</a>
                    @else
                        {{ $usage['owner']->displayTitle() }}
                    @endif
                    – {{ $usage['context'] }}@if (method_exists($usage['owner'], 'trashed') && $usage['owner']->trashed()) (im Papierkorb)@endif
                </li>
            @endforeach
        </ul>
    @endif
</section>
