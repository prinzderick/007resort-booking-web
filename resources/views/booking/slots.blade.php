@use('App\Support\Money')
@use('App\Support\Lagos')
@extends('layouts.app')
@section('title', ($resource['name'] ?? 'Choose a time').' - '.$facility['name'])
@section('noindex', '1')
@section('no_cta', '1')

@php
    $withQty = ($resource['mode'] ?? '') === 'INDIVIDUAL_CAPACITY';
    $holdMin = max(1, (int) round(config('r007.booking.fallback_hold_seconds') / 60));
    $players = max(1, min((int) ($resource['capacity'] ?? 50), (int) request('players', 1)));
@endphp

@section('content')
<div class="wrap">
    <div class="book-head">
        <nav class="crumbs" aria-label="Breadcrumb" style="color:var(--mute)"><a href="{{ route('book.resources', $facility['slug']) }}">{{ $facility['name'] }}</a><span aria-hidden="true">/</span><span aria-current="page">{{ $resource['name'] ?? 'Time' }}</span></nav>
        <h1>{{ $resource['name'] ?? $facility['name'] }}</h1>
        @if ($resource)<p class="lede" style="margin-top:8px">{{ Money::format($resource['price']) }} &middot; {{ $resource['slotMinutes'] }} min slots &middot; {{ $day->format('l j F Y') }}</p>@endif
    </div>

    @if ($notice)
        <x-notice type="warn">{{ $notice }} <a href="{{ route('contact') }}">Contact us</a></x-notice>
    @else
        <form id="date-jump-form" method="GET" action="{{ route('book.slots', [$facility['slug'], $resource['id']]) }}"></form>
        <form class="book-layout" method="POST" action="{{ route('book.hold', [$facility['slug'], $resource['id']]) }}" data-once data-slot-form>
            @csrf
            <x-idem />
            <div>
                @if (count($siblings) > 1)
                    <h2 class="step-label"><b>1</b> {{ ucfirst($facility['noun'] ?? 'Choose') }}</h2>
                    <div class="court-tabs">
                        @foreach ($siblings as $r)
                            <a class="court {{ ($r['onlineAvailable'] ?? true) === false ? 'is-paused' : '' }}" href="{{ route('book.slots', ['slug' => $facility['slug'], 'resourceId' => $r['id'], 'date' => $day->format('Y-m-d')]) }}" @if ($r['id'] === $resource['id']) aria-current="true" @endif>
                                <b>{{ $r['name'] }}</b><span>{{ Money::format($r['price']) }} &middot; {{ $r['slotMinutes'] }} min</span>
                            </a>
                        @endforeach
                    </div>
                @endif

                <h2 class="step-label"><b>{{ count($siblings) > 1 ? 2 : 1 }}</b> Date</h2>
                @include('partials.day-picker', ['externalForm' => true, 'days' => $days, 'day' => $day, 'url' => fn ($d) => route('book.slots', array_filter(['slug' => $facility['slug'], 'resourceId' => $resource['id'], 'date' => $d, 'players' => request('players')]))])

                <h2 class="step-label"><b>{{ count($siblings) > 1 ? 3 : 2 }}</b> Time</h2>
                <div aria-live="polite">
                    @include('partials.slot-grid', ['slots' => $slots, 'resource' => $resource])
                </div>
            </div>

            <aside class="book-side" aria-label="Your booking">
                <div class="summary" data-summary>
                    <h2>Your booking</h2>
                    <dl>
                        <div><dt>{{ ucfirst($facility['noun'] ?? 'Service') }}</dt><dd>{{ $resource['name'] }}</dd></div>
                        <div><dt>Date</dt><dd>{{ $day->format('D j M Y') }}</dd></div>
                        <div><dt>Time</dt><dd data-sum-time>Pick a time</dd></div>
                        @if ($withQty)
                            <div><dt><label for="quantity">People</label></dt>
                                <dd><span class="qty" data-qty><button type="button" aria-label="Fewer" data-qty-dec>&minus;</button><input id="quantity" name="quantity" type="number" inputmode="numeric" min="1" max="{{ $resource['capacity'] ?? 50 }}" value="{{ $players }}"><button type="button" aria-label="More" data-qty-inc>+</button></span></dd></div>
                        @endif
                    </dl>
                    <p class="total"><span>Total</span><b data-sum-total data-unit="{{ Money::minor($resource['price']) }}" data-qty-sync="{{ $withQty ? 1 : 0 }}">{{ Money::format($resource['price']) }}</b></p>
                    <button class="btn btn--lg btn--block" style="margin-top:18px" type="submit" data-busy="Holding your slot..." data-sum-submit>Hold this slot <span class="arr" aria-hidden="true">&rarr;</span></button>
                    <div class="hold-note" role="note"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg><span>We hold your slot for {{ $holdMin }} minutes while you pay, so nobody else can take it.</span></div>
                    @if (! empty($facility['addons']))
                        <div class="addons"><h3>Add-ons at the venue</h3>
                            <ul>@foreach ($facility['addons'] as $a)<li><span>{{ $a['name'] }}</span><span style="color:var(--mute)">{{ $a['note'] }}</span></li>@endforeach</ul>
                            <p>Show your booking QR at the Sports Store; rentals are not part of this payment.</p></div>
                    @endif
                </div>
            </aside>
        </form>
    @endif
</div>
@endsection
