<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Upload-Nachweis – {{ $receipt->title }}</title>
    <meta name="robots" content="noindex, nofollow">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="budget-proof">
<main class="proof-sheet">
    <div class="proof-actions"><a href="{{ route('admin.budget-plan.edit', $plan->id) }}">Zurück zum Haushaltsplan</a><button class="button" type="button" data-print>Nachweis drucken</button></div>
    <p class="cms-eyebrow">Gemeinde Merching · Verwaltung</p>
    <h1>Upload-Nachweis</h1>
    <h2>{{ $receipt->title }}</h2>
    <p>Gespeicherter Veröffentlichungsstand #{{ $receipt->id }}. Spätere Änderungen am Haushaltsplan verändern diesen Nachweis nicht.</p>
    <dl class="proof-facts">
        <div><dt>Haushaltsjahr</dt><dd>{{ $receipt->year }}</dd></div>
        <div><dt>Veröffentlichungsstatus</dt><dd>{{ $receipt->publish_at->greaterThan($receipt->created_at) ? 'Veröffentlichung geplant' : 'Veröffentlicht' }}</dd></div>
        <div><dt>Öffentlich ab</dt><dd>{{ \App\Support\SiteTime::format($receipt->publish_at, 'd.m.Y H:i:s') }} (Europe/Berlin)</dd></div>
        @if ($receipt->expires_at)<div><dt>Öffentlich bis</dt><dd>{{ \App\Support\SiteTime::format($receipt->expires_at, 'd.m.Y H:i:s') }} (Europe/Berlin)</dd></div>@endif
        <div><dt>Veröffentlichung gespeichert</dt><dd>{{ \App\Support\SiteTime::format($receipt->created_at, 'd.m.Y H:i:s') }} (Europe/Berlin)</dd></div>
        <div><dt>Veröffentlicht durch</dt><dd>{{ $receipt->publisher_name }}@if ($receipt->published_by) (Benutzer-ID {{ $receipt->published_by }})@endif</dd></div>
        <div><dt>Öffentliche URL</dt><dd><a href="{{ $receipt->public_url }}">{{ $receipt->public_url }}</a></dd></div>
        <div><dt>Erstellungsstatus</dt><dd>Erfolgreich erstellt · Generation #{{ $generation->id }}</dd></div>
        <div><dt>Gesamt-PDF erstellt</dt><dd>{{ \App\Support\SiteTime::format($generation->created_at, 'd.m.Y H:i:s') }} (Europe/Berlin)</dd></div>
        <div><dt>Barrierefreiheit des Gesamt-PDF</dt><dd>{{ $receipt->accessibility_status->label() }}@if ($receipt->accessibility_notes) – {{ $receipt->accessibility_notes }}@endif</dd></div>
    </dl>
    <section class="proof-file" aria-labelledby="combined-heading">
        <h2 id="combined-heading">Generiertes Gesamt-PDF</h2>
        <p><a href="{{ route('admin.budget-plan.file', [$plan->id, 'gesamt', $generation->id]) }}">{{ $generation->original_filename }}</a></p>
        <p>{{ number_format($generation->size_bytes, 0, ',', '.') }} Bytes · {{ $generation->page_count }} Seiten</p>
        <p><strong>SHA-256</strong><br><code>{{ $generation->sha256 }}</code></p>
    </section>
    <section aria-labelledby="sources-heading"><h2 id="sources-heading">Original-PDFs in Veröffentlichungsreihenfolge</h2>
        <ol class="proof-sources">
            @foreach ($generation->sources as $source)
                <li class="proof-file">
                    <h3><a href="{{ route('admin.budget-plan.file', [$plan->id, 'quelle', $source['id']]) }}">{{ $source['original_filename'] }}</a></h3>
                    <p>{{ number_format($source['size_bytes'], 0, ',', '.') }} Bytes · {{ $source['page_count'] }} Seiten · Upload #{{ $source['id'] }}</p>
                    <p>Hochgeladen: {{ \App\Support\SiteTime::format(\Carbon\CarbonImmutable::parse($source['uploaded_at']), 'd.m.Y H:i:s') }} (Europe/Berlin)</p>
                    <p><strong>SHA-256</strong><br><code>{{ $source['sha256'] }}</code></p>
                </li>
            @endforeach
        </ol>
    </section>
    <footer>Interner Upload-Nachweis aus gespeicherten Datei- und Veröffentlichungsdaten. Die Prüfsummen identifizieren die gespeicherten Dateien; dieser Nachweis ist keine digitale Signatur.</footer>
</main>
</body>
</html>
