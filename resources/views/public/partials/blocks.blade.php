@foreach ($model->blocks()->get() as $block)
    @php $target = $block->referenced(); $publicTarget = $target && method_exists($target, 'isPubliclyReachable') && $target->isPubliclyReachable(); @endphp
    @switch ($block->type)
        @case ('text')
            {!! \App\Support\Content\SafeMarkdown::toHtml($block->text) !!}
            @break
        @case ('heading')
            @if ($block->heading_level === 2)<h2>{{ $block->heading }}</h2>
            @elseif ($block->heading_level === 3)<h3>{{ $block->heading }}</h3>
            @elseif ($block->heading_level === 4)<h4>{{ $block->heading }}</h4>
            @endif

            @break
        @case ('callout')
            <aside aria-label="Hinweis">@if ($block->heading)<p><strong>{{ $block->heading }}</strong></p>
            @endif
                {!! \App\Support\Content\SafeMarkdown::toHtml($block->text) !!}</aside>
            @break
        @case ('accordion')
            <details><summary>{{ $block->heading }}</summary>{!! \App\Support\Content\SafeMarkdown::toHtml($block->text) !!}</details>
            @break
        @case ('image')
            @if ($publicTarget && $target->isImage() && $target->hasAccessibleAlternative())
                <figure>@include('public.partials.image', ['medium' => $target])getKey()) }}" alt="{{ $target->is_decorative ? '' : $target->alt_text }}" width="{{ $target->width }}" height="{{ $target->height }}" loading="lazy">
                    @if ($target->caption || $target->copyright)<figcaption>{{ $target->caption }}@if ($target->copyright) – {{ $target->copyright }}
                    @endif
                    </figcaption>
            @endif

                </figure>

            @endif

            @break
        @case ('gallery')
            @if ($publicTarget)@include('public.partials.gallery', ['gallery' => $target])
            @endif

            @break
        @case ('downloads')
            @if ($publicTarget)<p><a href="{{ \App\Support\Routing\PublicPath::toUrl($target->downloadPath()) }}">{{ $target->title }}</a> ({{ strtoupper($target->extension) }})</p>
            @endif

            @break
        @case ('contact')
        @case ('department')
            @if ($publicTarget)
                <div><p>{{ $target->displayTitle() }}</p>
                    @if ($target->phone)<p>Telefon: {{ $target->phone }}</p>
            @endif

                    @if ($target->email)<p>E-Mail: {{ $target->email }}</p>
            @endif

                </div>

            @endif

            @break
        @case ('services')
        @case ('events')
            @if ($publicTarget && $target->publicPath())<p><a href="{{ \App\Support\Routing\PublicPath::toUrl($target->publicPath()) }}">{{ $target->displayTitle() }}</a>@if ($target instanceof \App\Models\Event && $target->operational_status === \App\Enums\EventOperationalStatus::Cancelled) – Abgesagt
                @endif
                </p>
            @endif

            @break
        @case ('external')
            @if ($publicTarget)<p><a href="{{ $target->url }}">{{ $target->title }}</a>@if ($target->privacy_note) – {{ $target->privacy_note }}
                @endif
                </p>
            @endif

            @break
        @case ('location')
            @if ($publicTarget)@include('public.partials.location', ['location' => $target])
            @endif

            @break
    @endswitch
@endforeach
