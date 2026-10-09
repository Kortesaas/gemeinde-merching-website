{{-- Generic editorial page (also galleries, life situations fall back here when needed). --}}
@php $hasAside = $contacts->isNotEmpty() || $responsible; @endphp
@include('public.partials.page-header', ['eyebrow' => null, 'lead' => $model->getAttribute('summary')])
<div class="content-layout {{ $hasAside ? '' : 'content-layout--single' }}">
    <div class="content-main">
        @if ($model->getAttribute('body'))<div class="prose">{!! \App\Support\Content\SafeMarkdown::toHtml($model->getAttribute('body')) !!}</div>@endif
        @if (method_exists($model, 'blocks')) @include('public.partials.blocks') @endif
        @include('public.partials.downloads', ['owner' => $model])
        @include('public.partials.links', ['owner' => $model])
    </div>
    @if ($hasAside)
        <aside class="content-aside" aria-labelledby="aside-heading">
            <h2 id="aside-heading" class="aside-heading">Ansprechpersonen</h2>
            @if ($responsible) @include('public.partials.contact-card', ['contact' => $responsible]) @endif
            @foreach ($contacts as $person) @include('public.partials.contact-card', ['contact' => $person]) @endforeach
        </aside>
    @endif
</div>
