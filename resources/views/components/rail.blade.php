@props(['label' => 'Carousel', 'col' => null, 'grid' => false, 'autoplay' => false])
<div class="rail" data-rail @if ($autoplay) data-autoplay="{{ $autoplay }}" @endif>
    <div class="rail-track {{ $grid ? 'grid-lg' : '' }}" role="region" aria-roledescription="carousel" aria-label="{{ $label }}" tabindex="0" @if ($col) style="--col:{{ $col }}" @endif data-rail-track>
        {{ $slot }}
    </div>
    <div class="rail-nav" data-rail-nav></div>
</div>
