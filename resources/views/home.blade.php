@use('App\Support\Text')
@use('App\Support\Lagos')
@extends('layouts.app')

@section('hero', '1')

@php
    $cms = app(\App\Services\Online\SiteContext::class)->site();
    $slides = $by['HERO_SLIDE'] ?: [['headline' => $cms['brand']['tagline'] ?: config('site.name'), 'subheadline' => $cms['seo']['defaultDescription'] ?? '', 'media' => $content->fallbackHero(), 'ctaLabel' => $cms['booking']['bookingCtaLabel'], 'ctaLink' => '/sports', 'alignment' => 'LEFT']];
    $open = $cms['open'];
    $tz = config('r007.display_timezone', 'Africa/Lagos');
    $introTitle = $intro['found'] ? $intro['title'] : ($cms['brand']['tagline'] ?: 'Everything you came for, in one place.');
    $introText = $intro['found'] ? ($intro['subtitle'] ?: \App\Support\Text::excerpt($intro['bodyHtml'], 300)) : ($cms['seo']['defaultDescription'] ?? '');
@endphp

@push('head')
    <x-jsonld :data="['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => $cms['brand']['name'], 'url' => url('/')]" />
@endpush

@section('content')
<div class="main-stack">
    {{-- ---------------- hero slideshow: crossfade + Ken Burns, headline swaps per slide ---------------- --}}
    <section class="hero stick" data-hero aria-roledescription="carousel" aria-label="Featured at the resort">
        @foreach ($slides as $i => $s)
            <div class="hero-slide {{ $i === 0 ? 'is-active' : '' }}" data-slide aria-hidden="{{ $i === 0 ? 'false' : 'true' }}">
                <div class="ph" data-parallax="0.2"><x-img :m="$s['media'] ?? null" sizes="100vw" :eager="$i === 0" alt="" /></div>
            </div>
            @if ($i === 0) @include('partials.preload', ['m' => $s['media'] ?? null]) @endif
        @endforeach
        <div class="hero-stage">
            @foreach ($slides as $i => $s)
                @php $tag = $i === 0 ? 'h1' : 'p'; @endphp
                <div class="hero-slidecopy {{ $i === 0 ? 'is-active' : '' }}" data-slide-copy data-align="{{ strtolower($s['alignment'] ?? 'left') }}" @if ($i > 0) aria-hidden="true" @endif>
                    @if ($i === 0 && $open['known'])<span class="eyebrow">{{ $open['label'] }}</span>@endif
                    <{{ $tag }} class="ht">{{ Text::accent($s['headline']) }}</{{ $tag }}>
                    @if (! empty($s['subheadline']))<p class="sub">{{ $s['subheadline'] }}</p>@endif
                    <div class="row">
                        @if (! empty($s['ctaLabel']))<a class="btn btn--lg" href="{{ $s['ctaLink'] ?: '/sports' }}" data-magnetic @if ($i > 0) tabindex="-1" @endif>{{ $s['ctaLabel'] }} <span class="arr" aria-hidden="true">&rarr;</span></a>@endif
                        <a class="btn btn--lg btn--ghost" href="{{ route('pool') }}" @if ($i > 0) tabindex="-1" @endif>{{ $cms['booking']['ticketsCtaLabel'] }}</a>
                    </div>
                </div>
            @endforeach
        </div>
        @if ($open['known'])
            <aside class="now-card {{ $open['open'] ? '' : 'is-closed' }}" aria-label="Right now">
                <b>Right now</b>
                {{ $open['label'] }}<br>
                @if ($open['today'] && ! $open['today']['closed'])Today {{ \App\Support\OpenHours::fmt($open['today']['open']) }} to {{ \App\Support\OpenHours::fmt($open['today']['close']) }}<br>@endif
                @if (! empty($cms['hours']['notes'])){{ \Illuminate\Support\Str::of($cms['hours']['notes'])->before('.')->limit(60) }}.@endif
            </aside>
        @endif
        @if (count($slides) > 1)
            <div class="hero-ui" data-hero-ui>
                <button class="pp" type="button" aria-label="Pause slideshow" data-hero-pause><svg viewBox="0 0 12 12" aria-hidden="true"><rect x="1" y="1" width="3.5" height="10"/><rect x="7.5" y="1" width="3.5" height="10"/></svg></button>
                <div class="dots" role="group" aria-label="Choose slide">
                    @foreach ($slides as $i => $s)<button type="button" class="{{ $i === 0 ? 'is-active' : '' }}" aria-label="Slide {{ $i + 1 }}" @if ($i === 0) aria-current="true" @endif data-hero-dot="{{ $i }}"></button>@endforeach
                </div>
            </div>
        @endif
    </section>

    <div class="slide-over">
        {{-- ---------------- floating booking bar: what / when / players -> real availability ---------------- --}}
        <div class="bookbar-wrap" id="book">
            <form class="bookbar" method="GET" action="{{ route('book.now') }}" aria-label="Quick booking" data-bookbar>
                <label><span>What</span>
                    <select name="what" aria-label="What would you like to book">
                        @foreach ($bookable as $slug => $f)<option value="{{ $slug }}">{{ $f['name'] }}</option>@endforeach
                        @if (! count($bookable))<option value="sports-arena">Sports Arena</option>@endif
                    </select>
                </label>
                <label><span>When</span>
                    <input type="date" name="date" value="{{ Lagos::today()->format('Y-m-d') }}" min="{{ Lagos::today()->format('Y-m-d') }}" max="{{ Lagos::today()->addDays((int) config('r007.booking.horizon_days'))->format('Y-m-d') }}" aria-label="Date">
                </label>
                <label><span>Players</span>
                    <select name="players" aria-label="Number of players">
                        @foreach (range(1, 12) as $n)<option value="{{ $n }}" @selected($n === 2)>{{ $n }}</option>@endforeach
                    </select>
                </label>
                <button class="btn btn--lg" type="submit">{{ $cms['booking']['bookingCtaLabel'] ?: 'Check availability' }}</button>
            </form>
        </div>

        @if ($site['degraded'])
            <div class="wrap" style="padding-top:28px"><x-notice type="warn">Live availability is temporarily unavailable. You can still browse everything here; for bookings please try again shortly or call {{ $site['contact']['phone'] ?? 'reception' }}.</x-notice></div>
        @endif

        {{-- ---------------- intro + asymmetric mosaic ---------------- --}}
        <section class="section" aria-labelledby="intro-h">
            <div class="wrap">
                <div class="lead">
                    <div><span class="eyebrow">The resort</span><h2 id="intro-h" class="h-1 reveal">{{ Text::accent($introTitle) }}</h2></div>
                    <p class="lede reveal" style="--i:1">{{ $introText }}</p>
                </div>
                <div class="mosaic">
                    @foreach ($mosaic as $h)
                        <a class="tile reveal reveal--scale" style="--i:{{ $loop->index }}" href="{{ $h['link'] ?: '#' }}">
                            <div class="ph zoom"><x-img :m="$h['media'] ?? null" sizes="(min-width: 900px) 40vw, 90vw" alt="" /></div>
                            @if (! empty($h['category']))<span class="tag">{{ ucfirst($h['category']) }}</span>@endif
                            <span class="go" aria-hidden="true">&nearr;</span>
                            <div class="txt"><h3>{{ $h['title'] }}</h3><span class="d">{{ $h['priceFrom'] ?: $h['blurb'] }}</span></div>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- ---------------- marquee of activities (from CMS highlights + partners) ---------------- --}}
        @if (count($marquee))
            <div class="marquee" aria-hidden="true">
                <div class="marquee-track">
                    @for ($k = 0; $k < 2; $k++)
                        <div class="marquee-group">@foreach ($marquee as $m)<span>{{ $m }}</span><b>&#10045;</b>@endforeach</div>
                    @endfor
                </div>
            </div>
        @endif

        {{-- ---------------- highlights carousel ---------------- --}}
        @if (count($carousel))
            <section class="section" aria-labelledby="hl-h">
                <div class="wrap">
                    <div class="sec-head"><div><span class="eyebrow">Do more</span><h2 id="hl-h" class="h-1 reveal">A few of <i>our favourites.</i></h2></div></div>
                    <x-rail label="Highlights" grid>
                        @foreach ($carousel as $h)<div class="reveal" style="--i:{{ $loop->index }};display:flex">@include('partials.card-highlight', ['h' => $h])</div>@endforeach
                    </x-rail>
                </div>
            </section>
        @endif

        {{-- ---------------- stats: count-up ---------------- --}}
        @if (count($by['STAT']))
            <section class="section section--leaf cut-top" aria-label="The resort in numbers" style="padding-bottom:clamp(64px,8vw,110px)">
                <div class="wrap">
                    <div class="stats">
                        @foreach ($by['STAT'] as $st)
                            @php $n = Text::firstNumber($st['value']); @endphp
                            <div class="stat reveal" style="--i:{{ $loop->index }}">
                                <b>@if ($n)<span data-count="{{ $n['n'] }}" data-raw="{{ $n['raw'] }}" class="tnum">{{ $n['raw'] }}</span>@if ($n['rest'])<i @class(['sfx' => mb_strlen($n['rest']) > 1])>{{ $n['rest'] }}</i>@endif @else{{ $st['value'] }}@endif @if (! empty($st['suffix']))<i @class(['sfx' => mb_strlen($st['suffix']) > 1])>{{ $st['suffix'] }}</i>@endif</b>
                                <span>{{ $st['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        {{-- ---------------- after dark: dark photo section with the event list ---------------- --}}
        @include('partials.events-night', ['events' => $events, 'image' => $events[0]['cover'] ?? null, 'eyebrow' => 'This weekend', 'title' => 'After dark, it gets better.', 'body' => 'Live football, DJs on the pool deck and charcoal on the grill. Here is what is coming up.'])

        @include('partials.testimonials', ['items' => $by['TESTIMONIAL']])

        {{-- ---------------- places ---------------- --}}
        <section class="section--tight" aria-labelledby="places-h">
            <div class="wrap">
                <h2 id="places-h" class="h-3" style="margin-bottom:16px">Every place at the resort</h2>
                <div class="chips">
                    @foreach ($site['facilities'] as $slug => $f)<a class="chip" href="{{ route('facility', $slug) }}">{{ $f['name'] }}</a>@endforeach
                </div>
            </div>
        </section>

        @include('partials.photo-strip', ['items' => $strip, 'title' => 'Around the resort'])

        {{-- ---------------- membership block ---------------- --}}
        @if ($membership)
            <section class="section--tight" aria-labelledby="mem-h" style="padding-top:clamp(40px,6vw,80px)">
                <div class="wrap">
                    <div class="mem reveal" style="margin-inline:0;max-width:none">
                        <div>
                            <h2 id="mem-h">{{ Text::accent($membership['title']) }}</h2>
                            @if (! empty($membership['text']))<p>{{ $membership['text'] }}</p>@endif
                        </div>
                        <div class="end"><a class="btn btn--light btn--lg" href="{{ $membership['ctaLink'] }}" data-magnetic>{{ $membership['ctaLabel'] }} <span class="arr" aria-hidden="true">&rarr;</span></a></div>
                    </div>
                </div>
            </section>
        @endif

        @include('partials.journal-rail', ['posts' => $posts])

        @if (count($faqs))
            <section class="section section--paper2 curve-top" aria-labelledby="faq-h">
                <div class="wrap">
                    <div class="lead">
                        <div><span class="eyebrow">Questions</span><h2 id="faq-h" class="h-1 reveal">Good <i>to know.</i></h2><p style="margin-top:16px"><a class="link-arrow" href="{{ route('faq') }}">All questions <span aria-hidden="true">&rarr;</span></a></p></div>
                        <div class="reveal">@include('partials.faq', ['faqs' => $faqs, 'open' => true])</div>
                    </div>
                </div>
            </section>
        @endif

        @foreach ($ctaBands as $band)@include('partials.cta-band', ['band' => $band])@endforeach

        <section class="section--tight" style="padding-bottom:clamp(56px,8vw,110px)"><div class="wrap"><x-subscribe source="blog" variant="light" /></div></section>
    </div>
</div>
@endsection
