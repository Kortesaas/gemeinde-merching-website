@php
    $model->loadMissing(['category', 'tags', 'media']);
    $image = $model->media->first(fn ($m) => $m->isPubliclyReachable() && $m->isImage() && $m->hasAccessibleAlternative());
    $archived = $model->isInPublicArchive();
    $more = \App\Models\Article::query()->visible()->with(['canonicalRoute', 'category'])->whereKeyNot($model->getKey())->latest('publish_at')->limit(3)->get()->filter(fn ($a) => $a->publicPath() !== null);
    $hasAside = $contacts->isNotEmpty() || $responsible || $more->isNotEmpty();
@endphp
@include('public.partials.page-header', ['eyebrow' => 'Meldung · '.\App\Support\SiteTime::formatLocalized($model->publish_at).($model->category ? ' · '.$model->category->name : ''), 'lead' => $model->summary])
@if ($archived)<p class="notice-box"><x-icon name="history" /> Archiv: Diese Meldung ist nicht mehr aktuell.</p>@endif
<div class="content-layout {{ $hasAside ? '' : 'content-layout--single' }}">
    <div class="content-main">
        @if ($image)
            <figure class="lead-image">
                @include('public.partials.image', ['medium' => $image, 'imageLoading' => 'eager'])
                @if ($image->caption || $image->copyright)<figcaption>{{ $image->caption }}@if ($image->caption && $image->copyright) · @endif @if ($image->copyright)<span class="copyright">© {{ $image->copyright }}</span>@endif</figcaption>@endif
            </figure>
        @endif
        @if ($model->body)<div class="prose">{!! \App\Support\Content\SafeMarkdown::toHtml($model->body) !!}</div>@endif
        @include('public.partials.blocks')
        @include('public.partials.downloads', ['owner' => $model])
        @include('public.partials.links', ['owner' => $model])
        @if ($model->tags->isNotEmpty())
            <p class="tag-list"><span class="visually-hidden">Schlagwörter:</span>@foreach ($model->tags as $tag)<span class="tag">{{ $tag->name }}</span>@endforeach</p>
        @endif
        @if ($model->author_name)<p class="meta">Text: {{ $model->author_name }}</p>@endif
    </div>
    @if ($hasAside)
        <aside class="content-aside" aria-label="Ansprechpersonen und weitere Meldungen">
            @if ($contacts->isNotEmpty() || $responsible)
                <div class="aside-block">
                    <h2 class="aside-heading">Ansprechpersonen</h2>
                    @if ($responsible) @include('public.partials.contact-card', ['contact' => $responsible]) @endif
                    @foreach ($contacts as $person) @include('public.partials.contact-card', ['contact' => $person]) @endforeach
                </div>
            @endif
            @if ($more->isNotEmpty())
                <div class="aside-block">
                    <h2 class="aside-heading">Weitere Meldungen</h2>
                    <ul class="aside-links">
                        @foreach ($more as $other)
                            <li><a href="{{ \App\Support\Routing\PublicPath::toUrl($other->publicPath()) }}">{{ $other->displayTitle() }}</a><span class="meta">{{ \App\Support\SiteTime::formatLocalized($other->publish_at) }}</span></li>
                        @endforeach
                    </ul>
                    <a class="more-link" href="{{ app(\App\Services\Content\PublicCatalog::class)->sectionPath('articles') }}">Alle Meldungen <x-icon name="arrow-right" /></a>
                </div>
            @endif
        </aside>
    @endif
</div>
