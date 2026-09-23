@use('App\Support\Lagos')
@use('App\Support\Money')
@extends('layouts.app')
@section('title', 'Checkout')
@section('noindex', '1')

@section('content')
    <h1 class="text-3xl font-semibold">Checkout</h1>

    @php $live = $secondsLeft > 0 && in_array($booking['status'], ['HELD', 'PENDING_PAYMENT'], true); @endphp

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <section class="rounded-xl border border-stone-200 bg-white p-6 lg:col-span-2" aria-labelledby="sum-h">
            <h2 id="sum-h" class="font-semibold">Your booking</h2>
            <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                <div><dt class="text-stone-500">What</dt><dd class="font-medium">{{ $booking['resourceName'] ?? 'Booking' }}</dd></div>
                <div><dt class="text-stone-500">Reference</dt><dd class="font-medium">{{ $booking['number'] }}</dd></div>
                <div><dt class="text-stone-500">When</dt><dd class="font-medium">{{ Lagos::parse($booking['start'])->format('D j M Y') }}, {{ Lagos::parse($booking['start'])->format('H:i') }} - {{ Lagos::parse($booking['end'])->format('H:i') }}</dd></div>
                <div><dt class="text-stone-500">Quantity</dt><dd class="font-medium">{{ $booking['quantity'] ?? 1 }}</dd></div>
            </dl>
            <p class="mt-6 flex items-baseline justify-between border-t border-stone-100 pt-4 text-lg"><span>Total</span><strong>{{ Money::format($booking['total']) }}</strong></p>
        </section>

        <aside class="rounded-xl border p-6 {{ $live ? 'border-amber-300 bg-amber-50' : 'border-red-200 bg-red-50' }}">
            @if ($live)
                <p class="text-sm font-medium text-amber-900">We are holding your slot for</p>
                <p class="mt-1 text-4xl font-semibold tabular-nums text-amber-950" data-countdown="{{ $secondsLeft }}" role="timer" aria-live="off">--:--</p>
                <p class="sr-only" data-countdown-announce aria-live="polite"></p>
                <p class="mt-1 text-xs text-amber-900">Pay before the timer ends or the slot is released to others.</p>

                <form method="POST" action="{{ route('checkout.pay', $booking['id']) }}" data-once class="mt-5">
                    @csrf
                    <x-idem />
                    <button type="submit" data-busy="Redirecting to Paystack..." class="w-full rounded-lg bg-emerald-800 px-4 py-3 font-semibold text-white hover:bg-emerald-900 disabled:opacity-60">Pay {{ Money::format($booking['total']) }} with Paystack</button>
                </form>
                <form method="POST" action="{{ route('checkout.release', $booking['id']) }}" data-once class="mt-3">
                    @csrf
                    <x-idem />
                    <button type="submit" class="w-full rounded-lg border border-amber-400 px-4 py-2 text-sm hover:bg-amber-100">Release this slot</button>
                </form>
            @else
                <p class="font-semibold text-red-900">This hold has expired</p>
                <p class="mt-1 text-sm text-red-900">The slot was released so others can book it. Nothing has been charged.</p>
                <a class="mt-4 inline-block rounded-lg bg-emerald-800 px-4 py-2 text-sm font-semibold text-white" href="{{ $slug ? route('book.slots', [$slug, $booking['resourceId']]) : route('home') }}">Pick a new time</a>
            @endif
        </aside>
    </div>
@endsection
