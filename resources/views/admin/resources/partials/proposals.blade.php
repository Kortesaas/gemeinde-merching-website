{{-- Change proposals for published content. --}}
@php
    $user = auth()->user();
    $open = $model->proposals()->open()->with('author')->get()
        ->filter(fn ($p) => $p->status === \App\Enums\ProposalStatus::Submitted || $p->author_id === $user->getKey());
@endphp
<section class="editor-card" id="vorschlaege" aria-labelledby="proposals-heading">
    <h2 class="editor-card__title" id="proposals-heading">Änderungsvorschläge</h2>
    <div class="editor-card__body">
    @if ($open->isEmpty())
        <p class="meta">Keine offenen Vorschläge.</p>
    @else
        <ul class="cms-rows">
            @foreach ($open as $proposal)
                <li class="cms-row"><span class="state state--proposal-{{ $proposal->status->value }}">{{ $proposal->status->label() }}</span><div class="cms-row__body"><a class="cms-row__title" href="{{ route('admin.proposals.show', $proposal) }}">{{ $proposal->displayTitle() }}</a><p class="cms-row__meta">von {{ $proposal->author?->name ?? 'unbekannt' }}</p></div></li>
            @endforeach
        </ul>
    @endif
    @can('propose', $model)
        <form method="POST" action="{{ route('admin.'.$resource->key().'.proposals.store', $model->getKey()) }}">
            @csrf
            <p class="form-hint">Änderungen an veröffentlichten Inhalten werden als Vorschlag erfasst. Der veröffentlichte Stand bleibt bis zur Freigabe öffentlich.</p>
            <button type="submit" class="button">Änderung vorschlagen</button>
        </form>
    @endcan
    </div>
</section>
