@php $model->loadMissing(['category', 'links']); @endphp
@php $hasContact = (bool) ($model->contact_name || $model->street || $model->phone || $model->email); @endphp
@include('public.partials.page-header', ['eyebrow' => ($model->type?->label() ?? 'Organisation').($model->category ? ' · '.$model->category->name : ''), 'lead' => $model->description])
<div class="content-layout {{ $hasContact ? '' : 'content-layout--single' }}">
    <div class="content-main">
        @if ($model->website || $model->links->isNotEmpty())
            <section class="content-section" aria-labelledby="org-links"><h2 id="org-links">Im Internet</h2>
                <ul class="external-list">
                    @if ($model->website)<li><a class="external-link" href="{{ $model->website }}"><span class="external-link__text"><span class="external-link__title">Website von {{ $model->name }}<span class="visually-hidden"> (externer Link)</span></span><span class="external-link__meta">{{ parse_url($model->website, PHP_URL_HOST) }}</span></span><x-icon name="external" class="external-link__icon" /></a></li>@endif
                    @foreach ($model->links as $link)<li><a class="external-link" href="{{ $link->url }}"><span class="external-link__text"><span class="external-link__title">{{ $link->label }}<span class="visually-hidden"> (externer Link)</span></span></span><x-icon name="external" class="external-link__icon" /></a></li>@endforeach
                </ul>
                <p class="meta">Für die Inhalte externer Websites ist der jeweilige Anbieter verantwortlich.</p>
            </section>
        @endif
        @unless ($hasContact)<p class="meta">Keine weiteren Kontaktdaten veröffentlicht.</p>@endunless
    </div>
    @if ($hasContact)
    <aside class="content-aside" aria-labelledby="org-contact">
        <h2 id="org-contact" class="aside-heading">Kontakt</h2>
        @if ($model->contact_name)<p>{{ $model->contact_name }}</p>@endif
        @if ($model->street)<p>{{ $model->street }}<br>{{ $model->postal_code }} {{ $model->city }}</p>@endif
        @include('public.partials.contact-data', ['contact' => $model])
    </aside>
    @endif
</div>
