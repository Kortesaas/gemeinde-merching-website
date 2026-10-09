@php
    $focal = $medium->focalPoint();
    $widths = array_values(array_filter([480, 960, 1440], fn ($width) => $width < $medium->width));
    $widths[] = min(1440, $medium->width);
    $srcset = collect(array_unique($widths))->map(fn ($width) => route('public.media', ['media' => $medium->getKey(), 'width' => $width >= $medium->width ? null : $width]).' '.$width.'w')->implode(', ');
@endphp
<img src="{{ route('public.media', ['media' => $medium->getKey(), 'width' => $medium->width > 960 ? 960 : null]) }}" srcset="{{ $srcset }}" sizes="{{ $imageSizes ?? '(max-width: 40rem) calc(100vw - 2rem), 46rem' }}" class="{{ $imageClass ?? '' }} focal-x-{{ (int) round($focal['x']) }} focal-y-{{ (int) round($focal['y']) }}" alt="{{ $imageAlt ?? ($medium->is_decorative ? '' : $medium->alt_text) }}" width="{{ $medium->width }}" height="{{ $medium->height }}" loading="{{ $imageLoading ?? 'lazy' }}" decoding="async" @if ($imageEssential ?? false) data-display-essential @endif>
