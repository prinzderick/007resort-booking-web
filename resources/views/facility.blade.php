@use('App\Support\Text')
@extends('layouts.app')
@section('title', $facility['name'])
@section('description', $facility['tagline'].' '.$facility['description'])
@section('hero', '1')

@php
    $img = $cms['hero'] ?? $cms['heroFallback'] ?? null;
    $body = trim($cms['bodyHtml'] ?? '') !== '' && $cms['found'] ? $cms['bodyHtml'] : null;
    $phone = $facility['phone'] ?? ($site['contact']['phone'] ?? '');
    $hours = $facility['hours'] ?? ($ctx->site()['open']['known'] ? $ctx->site()['open']['label'] : $site['hours']);
@endphp

@push('head')
<x-jsonld :data="['@context' => 'https://schema.org', '@type' => 'LocalBusiness', 'name' => $facility['name'].' - 007 Resort & Spa',
    'description' => $facility['description'], 'url' => url()->current(), 'telephone' => $phone, 'address' => $site['contact']['address'] ?? null, 'openingHours' => $facility['hours'] ?? $site['hours']]" />
<x-jsonld :data="['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => url('/')], ['@type' => 'ListItem', 'position' => 2, 'name' => $facility['name'], 'item' => url()->current()]]]" />
@endpush

@section('content')
    @include('partials.page-hero', ['image' => $img, 'eyebrow' => '007 Resort & Spa', 'title' => $facility['name'], 'sub' => $facility['tagline'], 'compact' => true, 'crumbs' => [['Home', route('home')], [$facility['name']]],
        'ctas' => ($facility['flow'] !== 'info' && $facility['online_available']) ? [[$facility['flow'] === 'tickets' ? 'Buy tickets' : 'Book now', $facility['flow'] === 'tickets' ? route('pool') : route('book.resources', $facility['slug'])]] : []])

    <div class="slide-over" style="margin-top:0;border-radius:0">
        <section class="section">
            <div class="wrap event-layout">
                <div>
                    <p class="lede reveal">{{ $facility['api_description'] ?? $facility['description'] }}</p>
                    @if ($body)<div class="prose reveal" style="margin-top:26px">{!! $body !!}</div>@endif

                    @if ($facility['flow'] !== 'info')
                        @if (! $facility['online_available'])
                            <x-notice type="warn" class="mt-6" style="margin-top:28px">{{ $facility['notice'] ?? 'Online booking for this facility is temporarily unavailable. Please call or visit reception; the rest of the site is working normally.' }}</x-notice>
                        @else
                            <p style="margin-top:28px"><a class="btn btn--lg" href="{{ $facility['flow'] === 'tickets' ? route('pool') : route('book.resources', $facility['slug']) }}" data-magnetic>{{ $facility['flow'] === 'tickets' ? 'Buy tickets' : 'Book now' }} <span class="arr" aria-hidden="true">&rarr;</span></a></p>
                        @endif
                    @else
                        <p class="panel panel--line" style="margin-top:28px">Walk in any time during opening hours, or <a href="{{ route('contact') }}" style="font-weight:600">contact us</a> to reserve a table or plan an event.</p>
                    @endif
                </div>
                <aside class="event-aside panel" aria-label="Visit information">
                    <dl>
                        <div><dt>Opening hours</dt><dd>{{ $hours }}</dd></div>
                        <div><dt>Phone</dt><dd><a href="tel:{{ preg_replace('/\s+/', '', $phone) }}">{{ $phone }}</a></dd></div>
                        <div><dt>Location</dt><dd>{{ $site['contact']['address'] ?? '' }}</dd></div>
                    </dl>
                </aside>
            </div>
        </section>

        @include('partials.photo-strip', ['items' => $strip, 'title' => 'Take a look'])

        <section class="section--tight" style="padding-bottom:clamp(56px,8vw,110px)">
            <div class="wrap">
                <h2 class="h-3" style="margin-bottom:16px">Also at the resort</h2>
                <div class="chips">@foreach ($others as $slug => $f)<a class="chip" href="{{ route('facility', $slug) }}">{{ $f['name'] }}</a>@endforeach<a class="chip" href="{{ route('contact') }}">Contact and directions</a></div>
            </div>
        </section>
    </div>
@endsection
