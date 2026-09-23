@extends('layouts.app')
@section('title', 'Payment')
@section('noindex', '1')

@push('head')
    @if (in_array($state, ['processing'], true))
        <meta http-equiv="refresh" content="4">
    @endif
@endpush

@section('content')
    <div class="mx-auto max-w-xl rounded-xl border bg-white p-8 text-center
        {{ in_array($state, ['failed', 'paid_hold_lost'], true) ? 'border-red-200' : (in_array($state, ['membership'], true) ? 'border-emerald-200' : 'border-stone-200') }}">
        @switch($state)
            @case('processing')
                <h1 class="text-xl font-semibold">Confirming your payment...</h1>
                <p class="mt-2 text-stone-600">This usually takes a few seconds. Please keep this page open. Do not pay again.</p>
                <p class="mt-4 text-sm text-stone-500" role="status">Checking with our booking system.</p>
                @break
            @case('delayed')
                <h1 class="text-xl font-semibold">Still confirming</h1>
                <p class="mt-2 text-stone-600">Your payment is taking longer than usual to confirm. You have not been charged twice. Check <a class="text-emerald-800 underline" href="{{ route('account.bookings') }}">My bookings</a> in a few minutes; your ticket will appear there. If it does not, contact us with reference <strong>{{ $reference ?? '' }}</strong>.</p>
                @break
            @case('unverified')
                <h1 class="text-xl font-semibold">We could not check your payment yet</h1>
                <p class="mt-2 text-stone-600">Our booking system did not answer just now. If you were charged, your booking will still be honoured: check <a class="text-emerald-800 underline" href="{{ route('account.bookings') }}">My bookings</a> shortly, or refresh this page. Reference: <strong>{{ $reference ?? '' }}</strong></p>
                <a href="{{ url()->full() }}" class="mt-4 inline-block rounded bg-emerald-800 px-4 py-2 text-white">Check again</a>
                @break
            @case('failed')
                <h1 class="text-xl font-semibold text-red-900">Payment was not completed</h1>
                <p class="mt-2 text-stone-600">You have not been charged. @if (($pending['kind'] ?? '') === 'booking') Your slot may still be held for a few minutes. @endif</p>
                <p class="mt-4">
                    @if (($pending['kind'] ?? '') === 'booking')
                        <a class="rounded bg-emerald-800 px-4 py-2 text-white" href="{{ route('checkout.show', $pending['id']) }}">Try paying again</a>
                    @else
                        <a class="rounded bg-emerald-800 px-4 py-2 text-white" href="{{ route('home') }}">Back to home</a>
                    @endif
                </p>
                @break
            @case('paid_hold_lost')
                <h1 class="text-xl font-semibold text-red-900">We received your payment but the slot was lost</h1>
                <p class="mt-2 text-stone-600">Your hold expired before payment finished and the slot is no longer yours. Our team has been notified and will refund you. Please contact us if you do not hear back.</p>
                <a class="mt-4 inline-block rounded border border-stone-300 px-4 py-2" href="{{ route('contact') }}">Contact us</a>
                @break
            @case('membership')
                <h1 class="text-xl font-semibold text-emerald-900">Welcome, member!</h1>
                <p class="mt-2 text-stone-600">Your {{ $membership['planName'] ?? '' }} membership is active until {{ \App\Support\Lagos::parse($membership['validUntil'])->format('j F Y') }}.</p>
                <a class="mt-4 inline-block rounded bg-emerald-800 px-4 py-2 text-white" href="{{ route('account') }}">Go to my account</a>
                @break
            @case('paid_unlinked')
                <h1 class="text-xl font-semibold text-emerald-900">Payment received</h1>
                <p class="mt-2 text-stone-600">Thank you. Sign in to see your booking and QR ticket in <a class="text-emerald-800 underline" href="{{ route('account.bookings') }}">My bookings</a>.</p>
                @break
            @default
                <h1 class="text-xl font-semibold">We could not find that payment</h1>
                <p class="mt-2 text-stone-600">If you were charged, your booking will appear in <a class="text-emerald-800 underline" href="{{ route('account.bookings') }}">My bookings</a>. Otherwise start again from the home page.</p>
        @endswitch
    </div>
@endsection
