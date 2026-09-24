@use('App\Support\Text')
@use('App\Support\Money')
@extends('layouts.app')
@section('title', Text::plain($page['title']))
@section('description', $page['seo']['description'] ?? $page['subtitle'] ?? '')
@section('og_image', $page['seo']['ogImage']['url'] ?? ($page['heroFallback']['url'] ?? ''))
@section('hero', '1')

@php
    $cta = ['sports' => [$ctx->site()['booking']['bookingCtaLabel'], '#courts'], 'spa' => ['Book a treatment', '#book'], 'dining' => ['Plan an event', route('contact')]][$slug];
@endphp
@push('head')
    <x-jsonld :data="['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => url('/')], ['@type' => 'ListItem', 'position' => 2, 'name' => Text::plain($page['title']), 'item' => url()->current()]]]" />
@endpush

@section('content')
    @include('partials.page-hero', ['image' => $page['hero'] ?? $page['heroFallback'], 'eyebrow' => ucfirst($slug === 'spa' ? 'Beauty spa' : ($slug === 'sports' ? 'Sports arena' : 'Dining')), 'title' => $page['title'], 'sub' => $page['subtitle'], 'ctas' => [[$cta[0], $cta[1]]], 'crumbs' => [['Home', route('home')], [Text::plain($page['title'])]]])

    <div class="slide-over" style="margin-top:0;border-radius:0">
        @if ($liveSlug)
            <section class="section" id="{{ $liveSlug === 'sports-arena' ? 'courts' : 'book' }}" aria-labelledby="live-h">
                <div class="wrap">
                    <div class="sec-head"><div><span class="eyebrow">Live availability</span><h2 id="live-h" class="h-1 reveal">{{ $liveSlug === 'sports-arena' ? 'Choose your ' : 'Choose your ' }}<i>{{ $liveSlug === 'sports-arena' ? 'court.' : 'treatment.' }}</i></h2></div>
                        <a class="link-arrow" href="{{ route('book.resources', $liveSlug) }}">All {{ $liveSlug === 'sports-arena' ? 'courts' : 'treatments' }} <span aria-hidden="true">&rarr;</span></a></div>
                    @if ($notice)
                        <x-notice type="warn">{{ $notice }} <a href="{{ route('contact') }}">Contact us</a></x-notice>
                    @elseif (! count($resources))
                        <p class="empty">Nothing is bookable online right now. Please contact reception.</p>
                    @else
                        <div class="grid">
                            @foreach ($resources as $r)
                                @php $img = \App\Services\Online\ContentService::photoFor($strip, $r['name'], $loop->index); $paused = ($r['onlineAvailable'] ?? true) === false; @endphp
                                <article class="card reveal" style="--i:{{ $loop->index % 6 }}">
                                    <div class="ph zoom"><x-img :m="$img" sizes="(min-width: 900px) 30vw, 90vw" alt="" /></div>
                                    <div class="card-body">
                                        <h3>{{ $r['name'] }}</h3>
                                        <div class="meta"><b style="color:var(--ink)">{{ Money::format($r['price']) }}</b><span>{{ $r['slotMinutes'] }} min</span>@if (($r['mode'] ?? '') === 'INDIVIDUAL_CAPACITY')<span>up to {{ $r['capacity'] }} people</span>@endif</div>
                                        @if ($paused)
                                            <p>{{ $r['onlineNotice'] ?? 'Online booking paused for this item.' }}</p>
                                        @else
                                            <p><a class="btn btn--sm" href="{{ route('book.slots', [$liveSlug, $r['id']]) }}">See times <span class="arr" aria-hidden="true">&rarr;</span></a></p>
                                        @endif
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>
        @endif

        @if (trim($page['bodyHtml']) !== '')
            <section class="section {{ $liveSlug ? 'section--paper2' : '' }}">
                <div class="wrap"><div class="narrow prose reveal">{!! $page['bodyHtml'] !!}</div></div>
            </section>
        @endif

        @include('partials.photo-strip', ['items' => $strip, 'title' => 'On the ground'])

        @if (count($highlights))
            <section class="section" aria-labelledby="hl-h">
                <div class="wrap">
                    <div class="sec-head"><div><span class="eyebrow">Worth trying</span><h2 id="hl-h" class="h-1 reveal">Popular <i>right now.</i></h2></div></div>
                    <x-rail label="Highlights" grid>
                        @foreach ($highlights as $h)<div style="display:flex">@include('partials.card-highlight', ['h' => $h])</div>@endforeach
                    </x-rail>
                </div>
            </section>
        @endif

        @if (count($events))
            @include('partials.events-night', ['events' => $events, 'image' => $events[0]['cover'] ?? null, 'eyebrow' => 'Coming up', 'title' => 'Play with *others.*', 'body' => null])
        @endif

        @if (count($faqs))
            <section class="section section--paper2" aria-labelledby="faq-h">
                <div class="wrap"><div class="lead"><div><span class="eyebrow">Questions</span><h2 id="faq-h" class="h-1 reveal">Before you <i>book.</i></h2></div><div>@include('partials.faq', ['faqs' => $faqs, 'open' => true])</div></div></div>
            </section>
        @endif

        @foreach (array_slice($bands, $slug === 'dining' ? 1 : 0, 1) as $band)@include('partials.cta-band', ['band' => $band])@endforeach
        <section class="section--tight" style="padding-bottom:clamp(56px,8vw,110px)"><div class="wrap"><x-subscribe source="blog" variant="light" /></div></section>
    </div>
@endsection
