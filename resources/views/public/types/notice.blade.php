@php
    $model->loadMissing('category');
    $archived = $model->isInPublicArchive();
    $official = $documents->filter(fn ($d) => $d->pivot?->slot === 'bekanntmachung');
    $attachments = $documents->reject(fn ($d) => $d->pivot?->slot === 'bekanntmachung');
    $services = $model->services()->with('canonicalRoute')->visible()->get()->filter(fn ($s) => $s->publicPath() !== null);
    $pages = $model->pages()->with('canonicalRoute')->visible()->get()->filter(fn ($p) => $p->publicPath() !== null);
@endphp
@include('public.partials.page-header', ['eyebrow' => 'Amtliche Bekanntmachung'.($model->category ? ' · '.$model->category->name : ''), 'lead' => $model->summary])
<dl class="meta-list">
    @if ($model->published_on)<div><dt>Bekannt gemacht am</dt><dd>{{ \App\Support\SiteTime::format($model->published_on, 'd.m.Y') }}</dd></div>@endif
    @if ($model->expires_at)<div><dt>Aushang bis</dt><dd>{{ \App\Support\SiteTime::format($model->expires_at, 'd.m.Y') }}</dd></div>@endif
</dl>
@if ($archived)<p class="notice-box"><x-icon name="history" /> Archiv: Diese Bekanntmachung ist nicht mehr aktuell.</p>@endif
<div class="content-layout content-layout--single">
    <div class="content-main">
        @if ($official->isNotEmpty())
            <section class="content-section" aria-labelledby="official-docs"><h2 id="official-docs" class="visually-hidden">Bekanntmachung</h2>
                <ul class="download-list download-list--featured">@foreach ($official as $document)<li>@include('public.partials.download-item', ['document' => $document])</li>@endforeach</ul>
            </section>
        @endif
        @if ($model->body)<div class="prose">{!! \App\Support\Content\SafeMarkdown::toHtml($model->body) !!}</div>@endif
        @include('public.partials.blocks')
        @include('public.partials.downloads', ['owner' => $model, 'documents' => $attachments])
        @if ($services->isNotEmpty() || $pages->isNotEmpty())
            <section class="content-section" aria-labelledby="related"><h2 id="related">Weitere Informationen</h2>
                <ul class="link-list">
                    @foreach ([...$services, ...$pages] as $related)
                        <li><a class="link-row" href="{{ \App\Support\Routing\PublicPath::toUrl($related->publicPath()) }}"><span class="link-row__text"><span class="link-row__title">{{ $related->displayTitle() }}</span></span><x-icon name="arrow-right" class="link-row__arrow" /></a></li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
</div>
