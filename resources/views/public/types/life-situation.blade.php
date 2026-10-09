@php $services = $model->services()->with(['canonicalRoute', 'departments'])->visible()->get()->filter(fn ($s) => $s->publicPath() !== null)->values(); @endphp
@include('public.partials.page-header', ['eyebrow' => 'Lebenslage', 'lead' => $model->summary])
<div class="content-layout content-layout--single">
    <div class="content-main">
        @if ($model->body)<div class="prose">{!! \App\Support\Content\SafeMarkdown::toHtml($model->body) !!}</div>@endif
        @if ($services->isNotEmpty())
            <section class="content-section" aria-labelledby="steps"><h2 id="steps">Was ist zu tun?</h2>
                <ol class="step-list">
                    @foreach ($services as $service)
                        <li>
                            <a class="link-row" href="{{ \App\Support\Routing\PublicPath::toUrl($service->publicPath()) }}">
                                <span class="link-row__text"><span class="link-row__title">{{ $service->displayTitle() }}</span>@if ($service->summary)<span class="link-row__meta">{{ $service->summary }}</span>@endif</span>
                                <x-icon name="arrow-right" class="link-row__arrow" />
                            </a>
                        </li>
                    @endforeach
                </ol>
            </section>
        @endif
        @include('public.partials.blocks')
        @include('public.partials.downloads', ['owner' => $model])
        @include('public.partials.links', ['owner' => $model])
    </div>
</div>
