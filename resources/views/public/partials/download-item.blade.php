@php
    $document->loadMissing(['canonicalRoute', 'replacedBy.canonicalRoute', 'accessibleAlternative.canonicalRoute']);
    $storage = app(\App\Services\Content\DocumentStorage::class);
    $status = $document->accessibility_status;
    $statusIcon = match ($status) { \App\Enums\AccessibilityStatus::Accessible => 'check', \App\Enums\AccessibilityStatus::NotAccessible => 'warning', \App\Enums\AccessibilityStatus::NotChecked => null, default => 'info' };
    $newer = $document->replacedBy?->isPubliclyReachable() && $storage->exists($document->replacedBy) ? $document->replacedBy : null;
    $alternative = $document->accessibleAlternative?->isPubliclyReachable() && $storage->exists($document->accessibleAlternative) ? $document->accessibleAlternative : null;
    $tag = $headingTag ?? 'p';
@endphp
<div class="download {{ $newer ? 'download--archived' : '' }}">
    <span class="download__type" aria-hidden="true">{{ strtoupper((string) $document->extension) }}</span>
    <div class="download__body">
        <{{ $tag }} class="download__title"><a href="{{ \App\Support\Routing\PublicPath::toUrl($document->downloadPath()) }}">{{ $document->title }}<span class="visually-hidden"> ({{ strtoupper((string) $document->extension) }}, {{ \App\Support\Content\PublicFormat::fileSize($document->size_bytes) }})</span></a></{{ $tag }}>
        @if ($showDescription ?? true) @if ($document->description)<p class="download__description">{{ $document->description }}</p>@endif @endif
        <p class="download__meta">
            <span>{{ strtoupper((string) $document->extension) }} · {{ \App\Support\Content\PublicFormat::fileSize($document->size_bytes) }}</span>
            @if ($document->document_date)<span>Stand {{ \App\Support\SiteTime::format($document->document_date, 'd.m.Y') }}</span>@elseif ($document->year)<span>{{ $document->year }}</span>@endif
            @if ($document->valid_from)<span>gültig ab {{ \App\Support\SiteTime::format($document->valid_from, 'd.m.Y') }}</span>@endif
            <span class="download__a11y download__a11y--{{ $status->value }}">@if ($statusIcon)<x-icon :name="$statusIcon" />@endif Barrierefreiheit: {{ $status->label() }}</span>
        </p>
        @if ($newer)<p class="download__note"><span class="badge badge--archive">Ältere Fassung</span> Neuere Fassung: <a href="{{ \App\Support\Routing\PublicPath::toUrl($newer->downloadPath()) }}">{{ $newer->title }}</a></p>@endif
        @if ($alternative)<p class="download__note"><a href="{{ \App\Support\Routing\PublicPath::toUrl($alternative->downloadPath()) }}">Barrierefreie Alternative: {{ $alternative->title }}</a></p>@endif
        @if ($document->accessibility_notes && ($showDescription ?? true))<p class="download__note">{{ $document->accessibility_notes }}</p>@endif
    </div>
</div>
