@props(['m' => null, 'sizes' => '100vw', 'eager' => false, 'alt' => null, 'fill' => false, 'ratio' => null, 'pos' => null])
@php
    $m = is_array($m) && ! empty($m['url']) ? $m : null;
    $variants = collect($m['variants'] ?? [])->filter(fn ($v) => ! empty($v['url']) && ! empty($v['width']))->sortBy('width')->values();
    $srcset = $variants->map(fn ($v) => $v['url'].' '.$v['width'].'w')->implode(', ');
    $src = $variants->last()['url'] ?? ($m['url'] ?? '');
    $alt = $alt ?? ($m['alt'] ?? '');
    $bg = preg_match('/^#[0-9a-f]{3,8}$/i', (string) ($m['dominantColor'] ?? '')) ? $m['dominantColor'] : null;
@endphp
@if ($m)
<img src="{{ $src }}" @if ($srcset !== '') srcset="{{ $srcset }}" sizes="{{ $sizes }}" @endif alt="{{ $alt }}"
     @if (! empty($m['width'])) width="{{ $m['width'] }}" @endif @if (! empty($m['height'])) height="{{ $m['height'] }}" @endif
     @if ($eager) fetchpriority="high" decoding="async" @else loading="lazy" decoding="async" @endif
     @if ($bg || $pos) style="@if ($bg)background-color:{{ $bg }};@endif @if ($pos)object-position:{{ $pos }};@endif" @endif
     {{ $attributes }}>
@endif
