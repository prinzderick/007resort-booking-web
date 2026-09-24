@use('App\Support\Text')
@use('App\Support\Lagos')
@extends('layouts.app')
@section('title', $event['title'])
@section('description', $event['seo']['description'] ?? $event['summary'])
@section('og_image', $event['seo']['ogImage']['url'] ?? ($event['cover']['url'] ?? ''))
@section('hero', '1')

@php
    $s = Lagos::parse($occ['startsAt']); $e = Lagos::parse($occ['endsAt']);
    $qs = http_build_query(['start' => $occ['startsAt'], 'end' => $occ['endsAt']]);
    $venue = $event['venue']['label'] ?? null;
@endphp

@push('head')
    <x-jsonld :data="array_filter(['@context' => 'https://schema.org', '@type' => 'Event', 'name' => $event['title'], 'description' => $event['summary'], 'startDate' => $occ['startsAtLocal'] ?? $occ['startsAt'], 'endDate' => $occ['endsAtLocal'] ?? $occ['endsAt'],
        'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode', 'eventStatus' => 'https://schema.org/EventScheduled', 'image' => $event['cover']['url'] ?? null, 'url' => url()->current(),
        'location' => ['@type' => 'Place', 'name' => $venue ?: $ctx->site()['brand']['name'], 'address' => $ctx->contact()['address'] ?? null],
        'organizer' => ['@type' => 'Organization', 'name' => $ctx->site()['brand']['name'], 'url' => url('/')],
        'offers' => ! empty($event['priceText']) ? ['@type' => 'Offer', 'url' => url()->current(), 'description' => $event['priceText'], 'availability' => 'https://schema.org/InStock'] : null])" />
    <x-jsonld :data="['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => url('/')], ['@type' => 'ListItem', 'position' => 2, 'name' => 'Events', 'item' => route('events.index')], ['@type' => 'ListItem', 'position' => 3, 'name' => $event['title'], 'item' => url()->current()]]]" />
@endpush

@section('content')
    @include('partials.page-hero', ['image' => $event['cover'] ?? null, 'eyebrow' => ucfirst(strtolower($event['category'] ?? 'Event')).' · '.$s->format('D j M'), 'title' => $event['title'], 'sub' => $event['summary'], 'compact' => true, 'crumbs' => [['Home', route('home')], ["What's on", route('events.index')], [$event['title']]]])
    <div class="slide-over" style="margin-top:0;border-radius:0">
        <section class="section">
            <div class="wrap event-layout">
                <article>
                    <div class="prose reveal">{!! $event['bodyHtml'] ?? '' !!}</div>
                    @if (count($event['nextOccurrences'] ?? []) > 1)
                        <h2 class="h-3" style="margin:40px 0 12px">More dates</h2>
                        <div class="chips">
                            @foreach ($event['nextOccurrences'] as $o)<a class="chip" href="{{ route('events.show', $event['slug']) }}?start={{ urlencode($o['startsAt']) }}" @if ($o['startsAt'] === $occ['startsAt']) aria-current="true" @endif>{{ Lagos::parse($o['startsAt'])->format('D j M') }}</a>@endforeach
                        </div>
                    @endif
                    <div class="share" style="margin-top:36px" aria-label="Share this event"><span style="color:var(--mute);font-size:14px">Share</span>
                        <a href="https://wa.me/?text={{ rawurlencode($event['title'].' '.url()->current()) }}" rel="noopener">WhatsApp</a>
                        <a href="https://x.com/intent/post?text={{ rawurlencode($event['title']) }}&url={{ rawurlencode(url()->current()) }}" rel="noopener">X</a>
                        <a href="https://www.facebook.com/sharer/sharer.php?u={{ rawurlencode(url()->current()) }}" rel="noopener">Facebook</a>
                        <button type="button" data-copy-link>Copy link</button>
                    </div>
                </article>
                <aside class="event-aside">
                    <div class="panel">
                        <dl>
                            <div><dt>When</dt><dd class="tnum">{{ $s->format('l j F Y') }}<br>{{ $s->format('g:ia') }} to {{ $e->format('g:ia') }}</dd></div>
                            @if ($venue)<div><dt>Where</dt><dd>{{ $venue }}</dd></div>@endif
                            @if (! empty($event['priceText']))<div><dt>Price</dt><dd>{{ $event['priceText'] }}</dd></div>@endif
                            @if (! empty($event['capacity']))<div><dt>Capacity</dt><dd>{{ $event['capacity'] }} people</dd></div>@endif
                        </dl>
                        <div class="stack" style="margin-top:22px">
                            <a class="btn btn--lg btn--block" href="{{ $link['href'] }}" @if ($link['external']) rel="noopener" target="_blank" @endif data-magnetic>{{ $link['label'] }} <span class="arr" aria-hidden="true">&rarr;</span></a>
                            <a class="btn btn--line btn--block" href="{{ route('events.ics', $event['slug']) }}?{{ $qs }}">Add to calendar (.ics)</a>
                        </div>
                    </div>
                </aside>
            </div>
        </section>
        @if (count($more))
            <section class="section section--paper2"><div class="wrap">
                <div class="sec-head"><h2 class="h-1">More <i>to do.</i></h2><a class="link-arrow" href="{{ route('events.index') }}">All events <span aria-hidden="true">&rarr;</span></a></div>
                <x-rail label="More events" grid>@foreach ($more as $m)<div style="display:flex">@include('partials.card-event', ['e' => $m])</div>@endforeach</x-rail>
            </div></section>
        @endif
        <section class="section--tight" style="padding-bottom:clamp(56px,8vw,110px)"><div class="wrap"><x-subscribe source="event" variant="light" /></div></section>
    </div>
@endsection
