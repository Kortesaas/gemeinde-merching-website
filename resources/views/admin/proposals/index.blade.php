@extends('layouts.admin')

@section('title', 'Freigaben')

@section('content')
    <header class="cms-page-header">
        <div><p class="cms-eyebrow"><a href="{{ route('admin.dashboard') }}">Dashboard</a></p><h1>Freigaben und Änderungsvorschläge</h1></div>
    </header>
    <x-status />
    <p class="cms-lead">Änderungen an veröffentlichten Inhalten werden als Vorschlag geprüft. Niemand gibt eigene Vorschläge frei.</p>

    <section class="cms-panel" aria-labelledby="review-queue-heading">
        <header class="cms-panel__header"><h2 id="review-queue-heading">Zur Prüfung</h2><span class="nav-count">{{ $toReview->count() }}</span></header>
        <ul class="cms-rows">
            @forelse ($toReview as $proposal)
                <li class="cms-row">
                    <span class="state state--review">In Prüfung</span>
                    <div class="cms-row__body">
                        <a class="cms-row__title" href="{{ route('admin.proposals.show', $proposal) }}">{{ $proposal->proposable?->displayTitle() ?? $proposal->displayTitle() }}</a>
                        <p class="cms-row__meta">{{ $proposal->displayTitle() }} · von {{ $proposal->author?->name ?? 'unbekannt' }} · eingereicht {{ \App\Support\SiteTime::format($proposal->submitted_at) }}@if ($proposal->summary) · „{{ $proposal->summary }}“@endif</p>
                    </div>
                    <a class="button button--secondary button--small" href="{{ route('admin.proposals.show', $proposal) }}">Prüfen<span class="visually-hidden">: {{ $proposal->proposable?->displayTitle() }}</span></a>
                </li>
            @empty
                <li class="cms-empty">Keine Vorschläge zur Prüfung.</li>
            @endforelse
        </ul>
    </section>

    <section class="cms-panel" aria-labelledby="mine-heading">
        <header class="cms-panel__header"><h2 id="mine-heading">Meine Vorschläge</h2></header>
        <ul class="cms-rows">
            @forelse ($mine as $proposal)
                <li class="cms-row">
                    <span class="state state--proposal-{{ $proposal->status->value }}">{{ $proposal->status->label() }}</span>
                    <div class="cms-row__body"><a class="cms-row__title" href="{{ route('admin.proposals.show', $proposal) }}">{{ $proposal->proposable?->displayTitle() ?? $proposal->displayTitle() }}</a>@if ($proposal->summary)<p class="cms-row__meta">{{ $proposal->summary }}</p>@endif</div>
                </li>
            @empty
                <li class="cms-empty">Sie haben noch keine Änderungen vorgeschlagen.</li>
            @endforelse
        </ul>
    </section>
@endsection
