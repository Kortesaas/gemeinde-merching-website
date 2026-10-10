@php
    // Consecutive reference blocks of one list type render as one list.
    $listTypes = ['downloads', 'services', 'events', 'external', 'contact', 'department', 'navigation'];
    $groups = [];
    $seenResources = [];
    foreach ($model->blocks()->get() as $block) {
        $target = $block->referenced();
        // These pages use the municipal crest in their heading instead of the
        // imported decorative illustration. All source files stay in the CMS.
        if (($block->type === 'image' && $block->sort_order === 0 && in_array($model->publicPath(), ['/ortsrecht', '/gemeindekurier'], true))
            || ($block->type === 'downloads' && in_array($block->document_id, $omitDocumentIds ?? [], true))) {
            continue;
        }
        // Reference blocks whose target is deleted or not public are omitted entirely.
        if (in_array($block->type, ['image', 'gallery', 'downloads', 'contact', 'department', 'services', 'events', 'external', 'location'], true)
            && ($target === null || ! method_exists($target, 'isPubliclyReachable') || ! $target->isPubliclyReachable())) {
            continue;
        }
        // A migrated shortcode can repeat the same downloads/links in one
        // section. Present each reference once; retain the stored composition.
        // A heading starts a new context in which a repeated reference is useful.
        if ($block->type === 'heading') {
            $seenResources = [];
        } elseif (in_array($block->type, ['downloads', 'external'], true)) {
            $referenceKey = $block->type.':'.$target->getKey();
            if (isset($seenResources[$referenceKey])) {
                continue;
            }
            $seenResources[$referenceKey] = true;
        }
        $presentation = $block->type;
        // Only standalone link paragraphs are navigation; mixed prose stays prose.
        if ($presentation === 'text' && preg_match('~^<p><a href="[^"]+">[^<]+</a></p>\s*$~u', \App\Support\Content\SafeMarkdown::toHtml($block->text))) {
            $presentation = 'navigation';
        }
        $last = array_key_last($groups);
        if ($last !== null && $presentation === 'text' && ($groups[$last]['type'] === 'editorial' || ($groups[$last]['type'] === 'image' && $groups[$last]['items'][0]['target']->isImage() && $groups[$last]['items'][0]['target']->hasAccessibleAlternative() && $groups[$last]['items'][0]['target']->height > $groups[$last]['items'][0]['target']->width))) {
            $groups[$last]['type'] = 'editorial';
            $groups[$last]['items'][] = ['block' => $block, 'target' => $target];
        } elseif ($last !== null && $presentation === 'image' && $target->isImage() && $target->hasAccessibleAlternative() && $groups[$last]['type'] === 'text') {
            $groups[$last]['type'] = 'editorial-reverse';
            $groups[$last]['items'][] = ['block' => $block, 'target' => $target];
        } elseif ($last !== null && in_array($presentation, $listTypes, true) && $groups[$last]['type'] === $presentation) {
            $groups[$last]['items'][] = ['block' => $block, 'target' => $target];
        } else {
            $groups[] = ['type' => $presentation, 'items' => [['block' => $block, 'target' => $target]]];
        }
    }
@endphp
@foreach ($groups as $group)
    @php ['block' => $block, 'target' => $target] = $group['items'][0]; @endphp
    @switch ($group['type'])
        @case ('table')
            @include('public.partials.controlled-table')
            @break
        @case ('editorial')
            <div class="editorial-block">
                @include('public.partials.block-image', ['medium' => $target])
                <div>@foreach (array_slice($group['items'], 1) as $item)<div class="prose">{!! \App\Support\Content\SafeMarkdown::toHtml($item['block']->text) !!}</div>@endforeach</div>
            </div>
            @break
        @case ('editorial-reverse')
            <div class="editorial-block editorial-block--reverse">
                <div class="prose">{!! \App\Support\Content\SafeMarkdown::toHtml($block->text) !!}</div>
                @include('public.partials.block-image', ['medium' => $group['items'][1]['target']])
            </div>
            @break
        @case ('navigation')
            @include('public.partials.block-navigation')
            @break
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
                @include('public.partials.block-image', ['medium' => $target])
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
            <ul class="external-list block-list">@foreach ($group['items'] as $item)<li>@include('public.partials.external-link', ['resource' => $item['target'], 'isForm' => $model->publicPath() === '/formulare'])</li>@endforeach</ul>
            @break
        @case ('location')
            @if ($target) @include('public.partials.location', ['location' => $target]) @endif
            @break
    @endswitch
@endforeach
