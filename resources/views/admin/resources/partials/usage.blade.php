{{-- "Wo wird dieser Eintrag verwendet?" --}}
@php $usages = ($model instanceof \App\Models\Document || $model instanceof \App\Models\ExternalResource || $model instanceof \App\Models\Media) ? app(\App\Services\Content\ContentUsage::class)->of($model) : app(\App\Services\Content\ReferenceProtection::class)->usages($model); @endphp
<section class="editor-card" aria-labelledby="usage-heading">
    <h2 class="editor-card__title" id="usage-heading">Wo wird dieser Eintrag verwendet?</h2>
    <div class="editor-card__body">
    @if ($usages === [])
        <p class="meta">Derzeit nirgends verwendet.</p>
    @else
        <ul class="usage-list">
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
    </div>
</section>
