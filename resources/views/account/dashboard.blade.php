@use('App\Support\Lagos')
@extends('layouts.app')
@section('title', 'My account')
@section('noindex', '1')
@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-3xl font-semibold">Hello, {{ explode(' ', trim($user['name'] ?? 'guest'))[0] }}</h1>
            <p class="text-sm text-stone-600">{{ $user['email'] ?? '' }}</p>
        </div>
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="rounded border border-stone-300 bg-white px-3 py-2 text-sm hover:bg-stone-100">Sign out</button></form>
    </div>

    @if ($unavailable)
        <x-notice type="warn" class="mt-6">Your bookings are temporarily unavailable to load. Please try again in a few minutes.</x-notice>
    @endif

    <section class="mt-8" aria-labelledby="up-h">
        <div class="flex items-baseline justify-between"><h2 id="up-h" class="text-xl font-semibold">Upcoming</h2><a class="text-sm text-emerald-800 underline" href="{{ route('account.bookings') }}">All bookings</a></div>
        <ul class="mt-3 space-y-3">
            @forelse ($upcoming as $b)
                @include('partials.booking-row', ['b' => $b])
            @empty
                <li class="rounded-xl border border-stone-200 bg-white p-4 text-stone-600">Nothing coming up. <a class="text-emerald-800 underline" href="{{ route('book.resources', 'sports-arena') }}">Book a court</a> or <a class="text-emerald-800 underline" href="{{ route('pool') }}">buy pool tickets</a>.</li>
            @endforelse
        </ul>
    </section>

    <section class="mt-8" aria-labelledby="mem-h">
        <h2 id="mem-h" class="text-xl font-semibold">Memberships</h2>
        <ul class="mt-3 space-y-3">
            @forelse ($memberships as $m)
                <li class="rounded-xl border border-stone-200 bg-white p-4">
                    <p class="font-medium">{{ $m['planName'] ?? 'Membership' }} <span class="text-sm text-stone-500">{{ $m['number'] }}</span></p>
                    <p class="text-sm text-stone-600">{{ ucfirst(strtolower(str_replace('_', ' ', $m['status']))) }} &middot; valid until {{ Lagos::parse($m['validUntil'])->format('j M Y') }}</p>
                </li>
            @empty
                <li class="rounded-xl border border-stone-200 bg-white p-4 text-stone-600">No memberships yet. <a class="text-emerald-800 underline" href="{{ route('memberships.index') }}">See plans</a>.</li>
            @endforelse
        </ul>
    </section>
@endsection
