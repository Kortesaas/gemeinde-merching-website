@php
    $rows = \App\Support\Content\ControlledTable::rows($block->text);
    $records = \App\Support\Content\ControlledTable::recordGroups($rows);
    $columns = count($rows[0]);
@endphp
@if ($records)
    <section class="record-section" aria-label="{{ $block->heading }}">
        <h2>{{ $block->heading }}</h2>
        <div class="record-grid">
            @foreach ($records as $record)
                <section class="record-panel" aria-labelledby="record-{{ $block->id }}-{{ $loop->index }}">
                    <div class="meta">{!! \App\Support\Content\SafeMarkdown::toHtml($record[0][0]) !!}</div>
                    <h3 id="record-{{ $block->id }}-{{ $loop->index }}">{!! preg_replace('~^<p>|</p>\s*$~', '', \App\Support\Content\SafeMarkdown::toHtml($record[0][1])) !!}</h3>
                    <dl class="record-facts">
                        @foreach (array_slice($record, 1) as $row)
                            <div><dt>{!! \App\Support\Content\SafeMarkdown::toHtml($row[0]) !!}</dt><dd>{!! \App\Support\Content\SafeMarkdown::toHtml($row[1]) !!}</dd></div>
                        @endforeach
                    </dl>
                </section>
            @endforeach
        </div>
    </section>
@else
    <div class="table-block">
        @if ($columns > 2)<p class="table-hint meta {{ $columns <= 3 ? 'table-hint--small-screen' : '' }}">Die Tabelle lässt sich seitlich scrollen. Mit der Tastatur: Tabelle fokussieren und Pfeiltasten verwenden.</p>@endif
        <div class="controlled-table-scroll" role="region" aria-label="{{ $block->heading }}" tabindex="0">
            <table class="controlled-table {{ $columns > 3 ? 'controlled-table--wide' : '' }} {{ $columns === 2 ? 'controlled-table--two-columns' : '' }}">
                <caption>{{ $block->heading }}</caption>
                <thead><tr>@foreach ($rows[0] as $cell)<th scope="col">{!! \App\Support\Content\SafeMarkdown::toHtml($cell) !!}</th>@endforeach</tr></thead>
                <tbody>@foreach (array_slice($rows, 1) as $row)<tr>@foreach ($row as $cell)<td>{!! \App\Support\Content\SafeMarkdown::toHtml($cell) !!}</td>@endforeach</tr>@endforeach</tbody>
            </table>
        </div>
    </div>
@endif
