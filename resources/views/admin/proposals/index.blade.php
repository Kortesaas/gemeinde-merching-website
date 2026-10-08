@extends('layouts.admin')

@section('title', 'Freigaben')

@section('content')
    <p><a href="{{ route('admin.dashboard') }}">Zur Übersicht</a></p>
    <h1>Freigaben und Änderungsvorschläge</h1>
    <x-status />

    <section aria-labelledby="review-queue-heading">
        <h2 id="review-queue-heading">Zur Prüfung ({{ $toReview->count() }})</h2>
        @if ($toReview->isEmpty())
            <p>Keine Vorschläge zur Prüfung.</p>
        @else
            <div class="table-wrapper">
                <table class="data-table">
                    <caption class="visually-hidden">Eingereichte Vorschläge, älteste zuerst</caption>
                    <thead><tr><th scope="col">Vorschlag</th><th scope="col">Inhalt</th><th scope="col">Von</th><th scope="col">Eingereicht</th></tr></thead>
                    <tbody>
                        @foreach ($toReview as $proposal)
                            <tr>
                                <td><a href="{{ route('admin.proposals.show', $proposal) }}">{{ $proposal->displayTitle() }}</a></td>
                                <td>{{ $proposal->proposable?->displayTitle() }}</td>
                                <td>{{ $proposal->author?->name }}</td>
                                <td>{{ \App\Support\SiteTime::format($proposal->submitted_at) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section aria-labelledby="mine-heading">
        <h2 id="mine-heading">Meine Vorschläge</h2>
        @if ($mine->isEmpty())
            <p>Sie haben noch keine Änderungen vorgeschlagen.</p>
        @else
            <ul>
                @foreach ($mine as $proposal)
                    <li><a href="{{ route('admin.proposals.show', $proposal) }}">{{ $proposal->displayTitle() }}: {{ $proposal->proposable?->displayTitle() }}</a> – {{ $proposal->status->label() }}</li>
                @endforeach
            </ul>
        @endif
    </section>
@endsection
