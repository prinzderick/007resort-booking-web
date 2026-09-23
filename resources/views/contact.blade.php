@extends('layouts.app')
@section('title', 'Contact & location')
@section('description', 'Find 007 Resort & Spa: address, phone, email and opening hours.')

@section('content')
    <h1 class="text-3xl font-semibold">Contact &amp; location</h1>
    <div class="mt-6 grid gap-6 md:grid-cols-2">
        <dl class="space-y-4 rounded-xl border border-stone-200 bg-white p-6">
            <div><dt class="text-sm text-stone-500">Address</dt><dd>{{ $site['contact']['address'] ?? '' }}</dd></div>
            <div><dt class="text-sm text-stone-500">Phone</dt><dd><a class="text-emerald-800 hover:underline" href="tel:{{ preg_replace('/\s+/', '', $site['contact']['phone'] ?? '') }}">{{ $site['contact']['phone'] ?? '' }}</a></dd></div>
            <div><dt class="text-sm text-stone-500">Email</dt><dd><a class="text-emerald-800 hover:underline" href="mailto:{{ $site['contact']['email'] ?? '' }}">{{ $site['contact']['email'] ?? '' }}</a></dd></div>
            <div><dt class="text-sm text-stone-500">Resort hours</dt><dd>{{ $site['hours'] }}</dd></div>
            @if (! empty($site['contact']['map_url']))
                <div><a class="text-emerald-800 underline" href="{{ $site['contact']['map_url'] }}" rel="noopener">Open in maps</a></div>
            @endif
        </dl>
        <div class="rounded-xl border border-stone-200 bg-white p-6">
            <h2 class="font-semibold">Hours by facility</h2>
            <ul class="mt-3 divide-y divide-stone-100 text-sm">
                @foreach ($site['facilities'] as $slug => $f)
                    <li class="flex justify-between gap-4 py-2"><a class="hover:underline" href="{{ route('facility', $slug) }}">{{ $f['name'] }}</a><span class="text-stone-600">{{ $f['hours'] ?? $site['hours'] }}</span></li>
                @endforeach
            </ul>
        </div>
    </div>
@endsection
