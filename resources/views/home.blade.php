@extends('layouts.app')

@section('title', 'Sports, spa, pool and dining')
@section('description', 'Book tennis and football courts, spa and salon appointments, pool day tickets and memberships at 007 Resort & Spa. Instant QR confirmation.')

@push('head')
<script type="application/ld+json">{!! json_encode([
    '@context' => 'https://schema.org', '@type' => 'Resort', 'name' => '007 Resort & Spa', 'url' => url('/'),
    'telephone' => $site['contact']['phone'] ?? null, 'email' => $site['contact']['email'] ?? null,
    'address' => $site['contact']['address'] ?? null, 'openingHours' => $site['hours'],
], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
@endpush

@section('content')
    <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-emerald-900 via-emerald-800 to-emerald-700 px-6 py-12 text-white sm:px-10 sm:py-16">
        <p class="text-sm font-medium uppercase tracking-widest text-amber-300">Otueke, Bayelsa</p>
        <h1 class="mt-2 max-w-2xl text-3xl font-semibold leading-tight sm:text-5xl">Play, unwind and dine at 007 Resort &amp; Spa</h1>
        <p class="mt-4 max-w-xl text-emerald-50">Reserve a court, book a treatment or buy pool tickets in a couple of minutes. You get a QR code for instant entry.</p>
        <div class="mt-6 flex flex-wrap gap-3">
            <a href="{{ route('book.resources', 'sports-arena') }}" class="rounded-lg bg-amber-400 px-5 py-3 font-semibold text-emerald-950 hover:bg-amber-300">Book a court</a>
            <a href="{{ route('pool') }}" class="rounded-lg border border-white/40 px-5 py-3 font-semibold hover:bg-white/10">Pool tickets</a>
            <a href="{{ route('book.resources', 'beauty-spa') }}" class="rounded-lg border border-white/40 px-5 py-3 font-semibold hover:bg-white/10">Spa &amp; salon</a>
        </div>
    </section>

    @if ($site['degraded'])
        <x-notice type="warn" class="mt-6">Live availability is temporarily unavailable. You can still browse everything here; for bookings please try again shortly or call {{ $site['contact']['phone'] ?? 'reception' }}.</x-notice>
    @endif

    <section class="mt-10" aria-labelledby="fac-h">
        <h2 id="fac-h" class="text-2xl font-semibold">Our facilities</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($site['facilities'] as $slug => $f)
                <a href="{{ route('facility', $slug) }}" class="group rounded-xl border border-stone-200 bg-white p-5 shadow-sm transition hover:border-emerald-600 hover:shadow">
                    <h3 class="font-semibold text-emerald-900 group-hover:underline">{{ $f['name'] }}</h3>
                    <p class="mt-1 text-sm text-stone-600">{{ $f['tagline'] }}</p>
                    <p class="mt-3 text-xs text-stone-500">{{ $f['hours'] ?? $site['hours'] }}</p>
                    @if ($f['flow'] !== 'info')
                        <p class="mt-3 text-sm font-medium {{ $f['online_available'] ? 'text-emerald-700' : 'text-amber-700' }}">
                            {{ $f['online_available'] ? 'Book online' : 'Online booking paused' }}
                        </p>
                    @endif
                </a>
            @endforeach
        </div>
    </section>

    <section class="mt-10 grid gap-4 rounded-2xl border border-amber-200 bg-amber-50 p-6 sm:grid-cols-2 sm:items-center">
        <div>
            <h2 class="text-xl font-semibold text-amber-950">Become a member</h2>
            <p class="mt-1 text-sm text-amber-900">Priority access and member rates across the resort.</p>
        </div>
        <div class="sm:text-right"><a href="{{ route('memberships.index') }}" class="inline-block rounded-lg bg-emerald-800 px-5 py-3 font-semibold text-white hover:bg-emerald-900">See plans</a></div>
    </section>
@endsection
