{{-- LCP: preload the responsive hero image. Props: $m (media array), $sizes --}}
@php
    $vs = collect($m['variants'] ?? [])->sortBy('width')->values();
    $srcset = $vs->map(fn ($v) => $v['url'].' '.$v['width'].'w')->implode(', ');
@endphp
@if (! empty($m['url']))
    @push('preload')
        <link rel="preload" as="image" href="{{ $vs->last()['url'] ?? $m['url'] }}" @if ($srcset !== '') imagesrcset="{{ $srcset }}" imagesizes="{{ $sizes ?? '100vw' }}" @endif fetchpriority="high">
    @endpush
@endif
