@php
    // Consecutive reference blocks of one list type render as one list.
    $listTypes = ['downloads', 'services', 'events', 'external', 'contact', 'department'];
    $groups = [];
    foreach ($model->blocks()->get() as $block) {
        $target = $block->referenced();
        // Reference blocks whose target is deleted or not public are omitted entirely.
        if (in_array($block->type, ['image', 'gallery', 'downloads', 'contact', 'department', 'services', 'events', 'external', 'location'], true)
            && ($target === null || ! method_exists($target, 'isPubliclyReachable') || ! $target->isPubliclyReachable())) {
            continue;
        }
        $last = array_key_last($groups);
        if ($last !== null && in_array($block->type, $listTypes, true) && $groups[$last]['type'] === $block->type) {
            $groups[$last]['items'][] = ['block' => $block, 'target' => $target];
        } else {
            $groups[] = ['type' => $block->type, 'items' => [['block' => $block, 'target' => $target]]];
        }
    }
@endphp
@foreach ($groups as $group)
    @php ['block' => $block, 'target' => $target] = $group['items'][0]; @endphp
    @switch ($group['type'])
        @case ('text')
            <div class="prose">{!! \App\Support\Content\SafeMarkdown::toHtml($block->text) !!}</div>
            @break
        @case ('heading')
            @if ($block->heading_level === 2)<h2 class="block-heading">{{ $block->heading }}</h2>
            @elseif ($block->heading_level === 3)<h3 class="block-heading">{{ $block->heading }}</h3>
            @elseif ($block->heading_level === 4)<h4 class="block-heading">{{ $block->heading }}</h4>
            @endif
            @break
        @case ('callout')
            <aside class="callout" aria-label="{{ $block->heading ?: 'Hinweis' }}">
                <x-icon name="info" class="callout__icon" />
                <div>
                    @if ($block->heading)<p class="callout__title">{{ $block->heading }}</p>@endif
                    <div class="prose">{!! \App\Support\Content\SafeMarkdown::toHtml($block->text) !!}</div>
                </div>
            </aside>
            @break
        @case ('accordion')
            <details class="accordion">
                <summary><span>{{ $block->heading }}</span><x-icon name="plus" class="accordion__plus" /><x-icon name="minus" class="accordion__minus" /></summary>
                <div class="accordion__body prose">{!! \App\Support\Content\SafeMarkdown::toHtml($block->text) !!}</div>
            </details>
            @break
        @case ('image')
            @if ($target && $target->isImage() && $target->hasAccessibleAlternative())
                <figure class="block-image">
                    @include('public.partials.image', ['medium' => $target])
                    @if ($target->caption || $target->copyright)<figcaption>{{ $target->caption }}@if ($target->caption && $target->copyright) · @endif @if ($target->copyright)<span class="copyright">© {{ $target->copyright }}</span>@endif</figcaption>@endif
                </figure>
            @endif
            @break
        @case ('gallery')
            @if ($target) @include('public.partials.gallery', ['gallery' => $target]) @endif
            @break
        @case ('downloads')
            <ul class="download-list">@foreach ($group['items'] as $item)<li>@include('public.partials.download-item', ['document' => $item['target']])</li>@endforeach</ul>
            @break
        @case ('contact')
        @case ('department')
            <div class="contact-grid">@foreach ($group['items'] as $item)@include('public.partials.contact-card', ['contact' => $item['target'], 'showResponsibilities' => true])@endforeach</div>
            @break
        @case ('services')
            <ul class="link-list block-list">
                @foreach ($group['items'] as $item)
                    @if ($item['target']->publicPath())
                        <li><a class="link-row" href="{{ \App\Support\Routing\PublicPath::toUrl($item['target']->publicPath()) }}"><span class="link-row__text"><span class="link-row__title">{{ $item['target']->displayTitle() }}</span>@if ($item['target']->summary)<span class="link-row__meta">{{ $item['target']->summary }}</span>@endif</span><x-icon name="arrow-right" class="link-row__arrow" /></a></li>
                    @endif
                @endforeach
            </ul>
            @break
        @case ('events')
            <ul class="event-list block-list">@foreach ($group['items'] as $item) @if ($item['target']->publicPath())<li>@include('public.partials.event-item', ['record' => $item['target'], 'headingTag' => 'p'])</li>@endif @endforeach</ul>
            @break
        @case ('external')
            <ul class="external-list block-list">@foreach ($group['items'] as $item)<li>@include('public.partials.external-link', ['resource' => $item['target']])</li>@endforeach</ul>
            @break
        @case ('location')
            @if ($target) @include('public.partials.location', ['location' => $target]) @endif
            @break
    @endswitch
@endforeach
