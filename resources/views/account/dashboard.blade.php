@use('App\Support\Lagos')
@extends('layouts.app')
@section('title', 'My account')
@section('noindex', '1')
@section('content')
<div class="page-pad"><div class="wrap">
    <div style="display:flex;flex-wrap:wrap;align-items:end;justify-content:space-between;gap:16px">
        <div>
            <span class="eyebrow">My account</span>
            <h1 class="h-1">Hello, {{ explode(' ', trim($user['name'] ?? 'guest'))[0] }}</h1>
            <p style="color:var(--mute);margin-top:6px">{{ $user['email'] ?? '' }}</p>
        </div>
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn--line btn--sm">Sign out</button></form>
    </div>

    @if ($unavailable)
        <x-notice type="warn" style="margin-top:24px">Your bookings are temporarily unavailable to load. Please try again in a few minutes.</x-notice>
    @endif

    <section style="margin-top:44px" aria-labelledby="up-h">
        <div class="sec-head" style="margin-bottom:16px"><h2 id="up-h" class="h-2">Upcoming</h2><a class="link-arrow" href="{{ route('account.bookings') }}">All bookings <span aria-hidden="true">&rarr;</span></a></div>
        <ul class="stack plain" role="list">
            @forelse ($upcoming as $b)
                @include('partials.booking-row', ['b' => $b])
            @empty
                <li class="panel panel--line">Nothing coming up. <a href="{{ url('/sports') }}" style="font-weight:600">Book a court</a> or <a href="{{ route('pool') }}" style="font-weight:600">buy pool tickets</a>.</li>
            @endforelse
        </ul>
    </section>

    <section style="margin-top:44px" aria-labelledby="mem-h">
        <h2 id="mem-h" class="h-2" style="margin-bottom:16px">Memberships</h2>
        <ul class="stack plain" role="list">
            @forelse ($memberships as $m)
                <li class="row-item">
                    <div><p style="font-weight:600">{{ $m['planName'] ?? 'Membership' }} <span style="color:var(--mute);font-weight:400">{{ $m['number'] }}</span></p>
                    <p style="font-size:14px;color:var(--mute)">valid until {{ Lagos::parse($m['validUntil'])->format('j M Y') }}</p></div>
                    <span class="status status--ok">{{ ucfirst(strtolower(str_replace('_', ' ', $m['status']))) }}</span>
                </li>
            @empty
                <li class="panel panel--line">No memberships yet. <a href="{{ route('memberships.index') }}" style="font-weight:600">See plans</a>.</li>
            @endforelse
        </ul>
    </section>
</div></div>
@endsection
