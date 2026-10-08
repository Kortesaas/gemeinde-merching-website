{{-- Change proposals for published content. --}}
@php
    $user = auth()->user();
    $open = $model->proposals()->open()->with('author')->get()
        ->filter(fn ($p) => $p->status === \App\Enums\ProposalStatus::Submitted || $p->author_id === $user->getKey());
@endphp
<section aria-labelledby="proposals-heading">
    <h2 id="proposals-heading">Änderungsvorschläge</h2>
    @if ($open->isEmpty())
        <p>Keine offenen Vorschläge.</p>
    @else
        <ul>
            @foreach ($open as $proposal)
                <li><a href="{{ route('admin.proposals.show', $proposal) }}">{{ $proposal->displayTitle() }}</a> von {{ $proposal->author?->name ?? 'unbekannt' }} – {{ $proposal->status->label() }}</li>
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
</section>
