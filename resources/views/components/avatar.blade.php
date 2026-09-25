@props(['url' => null, 'name' => null, 'size' => 44])
@php $src = \App\Support\Avatar::safeUrl($url); @endphp
<span {{ $attributes->class(['avatar']) }} style="--s:{{ (int) $size }}px" data-avatar>
    <span class="avatar__ini" aria-hidden="true">{{ \App\Support\Avatar::initials($name) }}</span>
    @if ($src)<img src="{{ $src }}" alt="" width="{{ (int) $size }}" height="{{ (int) $size }}" referrerpolicy="no-referrer" loading="lazy" decoding="async">@endif
</span>
