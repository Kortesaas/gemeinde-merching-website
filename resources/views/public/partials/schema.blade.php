{{-- Trusted Schema.org data rendered as escaped microdata; no inline JavaScript. --}}
<div hidden itemscope itemtype="https://schema.org/{{ $schema['@type'] }}" @isset($schema['@id']) itemid="{{ $schema['@id'] }}" @endisset @isset($property) itemprop="{{ $property }}" @endisset>
    @foreach ($schema as $key => $value)
        @continue(str_starts_with($key, '@') || $value === null || $value === '')
        @if (is_array($value))
            @include('public.partials.schema', ['schema' => $value, 'property' => $key])
        @elseif (in_array($key, ['url', 'logo', 'image', 'eventStatus'], true))
            <link itemprop="{{ $key }}" href="{{ $value }}">
        @else
            <meta itemprop="{{ $key }}" content="{{ $value }}">
        @endif
    @endforeach
</div>
