@use('App\Support\Text')
{{-- Props: $image (media), $eyebrow, $title, $sub, $ctas [[label,href,style]], $crumbs [[label,href]], $compact --}}
@include('partials.preload', ['m' => $image ?? null])
<section class="page-hero {{ ! empty($compact) ? 'page-hero--compact' : '' }}" aria-labelledby="hero-h">
    <div class="ph" data-parallax="0.18"><x-img :m="$image ?? null" sizes="100vw" eager alt="" /></div>
    <div class="in">
        @if (! empty($crumbs))
            <nav class="crumbs" aria-label="Breadcrumb">
                @foreach ($crumbs as $c)@if (! $loop->last)<a href="{{ $c[1] }}">{{ $c[0] }}</a><span aria-hidden="true">/</span>@else<span aria-current="page">{{ $c[0] }}</span>@endif @endforeach
            </nav>
        @endif
        @if (! empty($eyebrow))<span class="eyebrow">{{ $eyebrow }}</span>@endif
        <h1 id="hero-h" class="reveal">{{ Text::accent($title ?? '') }}</h1>
        @if (! empty($sub))<p class="sub reveal" style="--i:1">{{ $sub }}</p>@endif
        @if (! empty($ctas))
            <div class="row reveal" style="--i:2">
                @foreach ($ctas as $c)<a class="btn btn--lg {{ ($c[2] ?? 'primary') === 'ghost' ? 'btn--ghost' : '' }}" href="{{ $c[1] }}" data-magnetic>{{ $c[0] }}</a>@endforeach
            </div>
        @endif
    </div>
</section>
