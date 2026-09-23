@use('App\Support\Money')
@extends('layouts.app')
@section('title', 'Membership plans')
@section('description', 'Join 007 Resort & Spa as a member for priority access and member rates.')

@section('content')
    <h1 class="text-3xl font-semibold">Membership plans</h1>
    <p class="mt-1 text-stone-600">Pick a plan and pay securely online. Your membership QR is available in your account as soon as payment is confirmed.</p>

    @if ($notice)
        <x-notice type="warn" class="mt-6">{{ $notice }}</x-notice>
    @endif

    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($plans as $p)
            <article class="flex flex-col rounded-xl border border-stone-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold">{{ $p['name'] }}</h2>
                <p class="mt-2 text-3xl font-semibold text-emerald-900">{{ Money::format($p['price']) }}</p>
                <ul class="mt-3 flex-1 space-y-1 text-sm text-stone-600">
                    <li>Valid for {{ $p['durationDays'] }} days</li>
                    <li>{{ ($p['visitLimit'] ?? null) ? $p['visitLimit'].' visits' : 'Unlimited visits' }}</li>
                </ul>
                <form method="POST" action="{{ route('memberships.buy', $p['id']) }}" data-once class="mt-5">
                    @csrf
                    <x-idem />
                    <button type="submit" data-busy="Redirecting to Paystack..." class="w-full rounded-lg bg-emerald-800 px-4 py-3 font-semibold text-white hover:bg-emerald-900 disabled:opacity-60">Buy this plan</button>
                </form>
            </article>
        @endforeach
    </div>
    @if (! $notice && count($plans) === 0)
        <p class="mt-6 rounded-lg border border-stone-200 bg-white p-4 text-stone-600">No plans are on sale online right now. Please ask at reception.</p>
    @endif
@endsection
