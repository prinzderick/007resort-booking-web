@extends('layouts.app')
@section('title', $facility['name'])
@section('description', $facility['tagline'].' '.$facility['description'])

@push('head')
<script type="application/ld+json">{!! json_encode([
    '@context' => 'https://schema.org', '@type' => 'LocalBusiness', 'name' => $facility['name'].' - 007 Resort & Spa',
    'description' => $facility['description'], 'url' => url()->current(), 'telephone' => $facility['phone'] ?? ($site['contact']['phone'] ?? null),
    'address' => $site['contact']['address'] ?? null, 'openingHours' => $facility['hours'] ?? $site['hours'],
], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
@endpush

@section('content')
    <nav aria-label="Breadcrumb" class="text-sm text-stone-500"><a class="hover:underline" href="{{ route('home') }}">Home</a> / {{ $facility['name'] }}</nav>
    <h1 class="mt-2 text-3xl font-semibold">{{ $facility['name'] }}</h1>
    <p class="mt-1 text-lg text-stone-600">{{ $facility['tagline'] }}</p>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <p class="text-stone-700">{{ $facility['api_description'] ?? $facility['description'] }}</p>

            @if ($facility['flow'] !== 'info')
                @if (! $facility['online_available'])
                    <x-notice type="warn" class="mt-6">{{ $facility['notice'] ?? 'Online booking for this facility is temporarily unavailable. Please call or visit reception; the rest of the site is working normally.' }}</x-notice>
                @else
                    <div class="mt-6">
                        <a class="inline-block rounded-lg bg-emerald-800 px-5 py-3 font-semibold text-white hover:bg-emerald-900"
                           href="{{ $facility['flow'] === 'tickets' ? route('pool') : route('book.resources', $facility['slug']) }}">
                            {{ $facility['flow'] === 'tickets' ? 'Buy tickets' : 'Book now' }}
                        </a>
                    </div>
                @endif
            @else
                <p class="mt-6 rounded-lg bg-stone-100 p-4 text-sm text-stone-700">Walk in any time during opening hours, or call us to reserve a table or plan an event.</p>
            @endif
        </div>

        <aside class="rounded-xl border border-stone-200 bg-white p-5 text-sm" aria-label="Visit information">
            <h2 class="font-semibold">Visit</h2>
            <dl class="mt-3 space-y-3">
                <div><dt class="text-stone-500">Opening hours</dt><dd>{{ $facility['hours'] ?? $site['hours'] }}</dd></div>
                <div><dt class="text-stone-500">Phone</dt><dd><a class="text-emerald-800 hover:underline" href="tel:{{ preg_replace('/\s+/', '', $facility['phone'] ?? $site['contact']['phone'] ?? '') }}">{{ $facility['phone'] ?? $site['contact']['phone'] ?? '' }}</a></dd></div>
                <div><dt class="text-stone-500">Location</dt><dd>{{ $site['contact']['address'] ?? '' }}</dd></div>
            </dl>
        </aside>
    </div>
@endsection
