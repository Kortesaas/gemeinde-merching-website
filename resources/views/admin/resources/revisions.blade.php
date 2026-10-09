@extends('layouts.admin')

@section('title', 'Versionen – '.$model->displayTitle())

@section('content')
    <div class="editor-bar">
        <a class="editor-bar__back" href="{{ route('admin.'.$resource->key().'.edit', $model->getKey()) }}"><x-icon name="arrow-left" /> Zurück zum Bearbeiten</a>
        @include('admin.partials.state-badge', ['model' => $model])
    </div>
    <p class="cms-eyebrow">Historie · {{ $resource->label() }}</p>
    <h1 class="editor-title">{{ $model->displayTitle() }}</h1>
    <p class="cms-lead">Jede gespeicherte Änderung bleibt erhalten. Eine ältere Version lässt sich als neue Version wiederherstellen; der Veröffentlichungsstatus ändert sich dabei nicht.</p>

    @if ($revisions->isEmpty())
        <div class="cms-empty-state"><p>Noch keine Versionen vorhanden.</p></div>
    @else
        <ol class="timeline" aria-label="Versionen, neueste zuerst">
            @foreach ($revisions as $revision)
                <li class="timeline__item {{ $loop->first ? 'is-current' : '' }}">
                    <span class="timeline__marker" aria-hidden="true">{{ $revision->revision_number }}</span>
                    <div class="timeline__body">
                        <p class="timeline__title"><a href="{{ route('admin.'.$resource->key().'.revisions.show', [$model->getKey(), $revision->revision_number]) }}">Version {{ $revision->revision_number }}</a>@if ($loop->first) <span class="state state--published">Aktueller Stand</span>@endif</p>
                        <p class="timeline__summary">{{ $revision->summary ?: 'Ohne Änderungsnotiz' }}</p>
                        <p class="cms-row__meta"><time datetime="{{ $revision->created_at->toIso8601String() }}">{{ \App\Support\SiteTime::format($revision->created_at) }}</time> · {{ $revision->user?->name ?? 'System' }}</p>
                    </div>
                    <a class="button button--secondary button--small" href="{{ route('admin.'.$resource->key().'.revisions.show', [$model->getKey(), $revision->revision_number]) }}">Ansehen<span class="visually-hidden">: Version {{ $revision->revision_number }}</span></a>
                </li>
            @endforeach
        </ol>
    @endif
@endsection
