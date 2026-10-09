@php
    $model->loadMissing(['category', 'tags', 'media']);
    $image = $model->media->first(fn ($m) => $m->isPubliclyReachable() && $m->isImage() && $m->hasAccessibleAlternative());
    $archived = $model->isInPublicArchive();
    $hasAside = $contacts->isNotEmpty() || $responsible;
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
        <aside class="content-aside" aria-labelledby="aside-heading">
            <h2 id="aside-heading" class="aside-heading">Ansprechpersonen</h2>
            @if ($responsible) @include('public.partials.contact-card', ['contact' => $responsible]) @endif
            @foreach ($contacts as $person) @include('public.partials.contact-card', ['contact' => $person]) @endforeach
        </aside>
    @endif
</div>
