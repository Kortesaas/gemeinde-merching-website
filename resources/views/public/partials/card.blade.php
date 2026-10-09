@php if (method_exists($record, 'canonicalRoute')) { $record->loadMissing('canonicalRoute'); } $path = $record instanceof \App\Models\Document ? $record->downloadPath() : (method_exists($record, 'publicPath') ? $record->publicPath() : null); @endphp
<div class="content-card">
    <p class="meta">{{ \App\Http\Controllers\Public\SearchController::LABELS[$record->getMorphClass()] ?? 'Einrichtung' }}
        @if ($record instanceof \App\Models\Article || $record instanceof \App\Models\PublicNotice) · {{ \App\Support\SiteTime::format($record->publish_at, 'd.m.Y') }}@endif
        @if ($record instanceof \App\Models\Event) · {{ \App\Support\SiteTime::format($record->starts_at, $record->all_day ? 'd.m.Y' : 'd.m.Y, H:i') }} {{ $record->all_day ? 'ganztägig' : 'Uhr' }}@endif
    </p>
    <{{ $headingTag ?? 'h2' }}>@if ($path)<a href="{{ \App\Support\Routing\PublicPath::toUrl($path) }}">{{ $record->displayTitle() }}</a>@else {{ $record->displayTitle() }} @endif</{{ $headingTag ?? 'h2' }}>
    @if ($record instanceof \App\Models\Event && $record->operational_status === \App\Enums\EventOperationalStatus::Cancelled)<p class="status-badge"><strong>Abgesagt</strong></p>@endif
    @if ($record instanceof \App\Models\Event && $record->schedule_notice)<p>{{ $record->schedule_notice }}</p>@endif
    @if (($record->getAttributes()['summary'] ?? null))<p>{{ ($record->getAttributes()['summary'] ?? null) }}</p>@endif
    @if ($record instanceof \App\Models\Document)
        @php $record->loadMissing(['replacedBy.canonicalRoute', 'accessibleAlternative.canonicalRoute']); @endphp
        @if ($record->description)<p>{{ $record->description }}</p>@endif
        <p class="meta">{{ strtoupper($record->extension) }} · {{ number_format(max(1, ceil($record->size_bytes / 1024)), 0, ',', '.') }} KB @if ($record->year) · {{ $record->year }}@endif @if($record->document_date) · {{ \App\Support\SiteTime::format($record->document_date, 'd.m.Y') }}@endif · Barrierefreiheit: {{ $record->accessibility_status->label() }}</p>
        @if ($record->replacedBy?->isPubliclyReachable() && app(\App\Services\Content\DocumentStorage::class)->exists($record->replacedBy))<p>Neuere Fassung: <a href="{{ \App\Support\Routing\PublicPath::toUrl($record->replacedBy->downloadPath()) }}">{{ $record->replacedBy->title }}</a></p>@endif
        @if ($record->accessibleAlternative?->isPubliclyReachable() && app(\App\Services\Content\DocumentStorage::class)->exists($record->accessibleAlternative))<p><a href="{{ \App\Support\Routing\PublicPath::toUrl($record->accessibleAlternative->downloadPath()) }}">Barrierefreie Alternative: {{ $record->accessibleAlternative->title }}</a></p>@endif
    @endif
    @if ($record instanceof \App\Models\Service)
        @foreach ($record->departments()->where('is_active', true)->get() as $department)<p class="meta">{{ $department->name }}@if ($department->phone) · {{ $department->phone }}@endif</p>@endforeach
    @endif
    @if ($record instanceof \App\Models\Person)<p>{{ $record->job_title }}</p>@if($record->responsibilities)<p>{{ $record->responsibilities }}</p>@endif @if($record->availability)<p>{{ $record->availability }}</p>@endif @endif
    @if ($record instanceof \App\Models\Location) @include('public.partials.location', ['location'=>$record]) @endif
    @if ($record instanceof \App\Models\Department && $record->opening_hours)<p>{{ $record->opening_hours }}</p>@endif
    @if ($record instanceof \App\Models\Person || $record instanceof \App\Models\Department || $record instanceof \App\Models\Organization) @include('public.partials.contact-data', ['contact' => $record]) @endif
</div>
