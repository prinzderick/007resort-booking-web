@extends('layouts.app')
@section('title', 'Payment')
@section('noindex', '1')

@push('head')
    @if (in_array($state, ['processing'], true))
        <meta http-equiv="refresh" content="4">
    @endif
@endpush

@section('content')
<div class="page-pad"><div class="wrap"><div class="pay-result panel">
    @switch($state)
        @case('processing')
            <div class="spinner" aria-hidden="true"></div>
            <h1>Confirming your payment...</h1>
            <p class="lede" style="margin:0 auto">This usually takes a few seconds. Please keep this page open. Do not pay again.</p>
            <p style="margin-top:16px;color:var(--mute);font-size:14px" role="status">Checking with our booking system.</p>
            @break
        @case('delayed')
            <h1>Still confirming</h1>
            <p class="lede" style="margin:0 auto">Your payment is taking longer than usual to confirm. You have not been charged twice. Give it a few minutes, then use <a href="{{ route('find.show') }}" style="font-weight:600">Find my booking</a>. If your ticket does not appear, contact us with reference <strong>{{ $reference ?? '' }}</strong>.</p>
            @break
        @case('unverified')
            <h1>We could not check your payment yet</h1>
            <p class="lede" style="margin:0 auto">Our booking system did not answer just now. If you were charged, your booking will still be honoured: check <a href="{{ route('find.show') }}" style="font-weight:600">Find my booking</a> shortly, or refresh this page. Reference: <strong>{{ $reference ?? '' }}</strong></p>
            <a href="{{ url()->full() }}" class="btn" style="margin-top:22px">Check again</a>
            @break
        @case('failed')
            <h1>Payment was not completed</h1>
            <p class="lede" style="margin:0 auto">You have not been charged. @if (! empty($order)) Your order is saved, so you can try again in one tap. @elseif (($pending['kind'] ?? '') === 'booking') Your slot may still be held for a few minutes. @endif</p>
            <p style="margin-top:22px">
                @if (! empty($order))
                    <a class="btn btn--lg" href="{{ route('orders.show', $order) }}">Try paying again</a>
                @elseif (($pending['kind'] ?? '') === 'booking')
                    <a class="btn btn--lg" href="{{ route('checkout.show', $pending['id']) }}">Try paying again</a>
                @else
                    <a class="btn btn--lg" href="{{ route('home') }}">Back to home</a>
                @endif
            </p>
            @break
        @case('paid_hold_lost')
            <h1>We received your payment but the slot was lost</h1>
            <p class="lede" style="margin:0 auto">Your hold expired before payment finished and the slot is no longer yours. Our team has been notified and will refund you. Please contact us if you do not hear back.</p>
            <a class="btn btn--line" style="margin-top:22px" href="{{ route('contact') }}">Contact us</a>
            @break
        @case('membership')
            <div class="done-hero" style="margin-bottom:12px"><div class="check"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></div></div>
            <h1>Welcome, member!</h1>
            <p class="lede" style="margin:0 auto">Your {{ $membership['planName'] ?? '' }} membership is active until {{ \App\Support\Lagos::parse($membership['validUntil'])->format('j F Y') }}.</p>
            <a class="btn btn--lg" style="margin-top:22px" href="{{ route('account') }}">Go to my account</a>
            @break
        @case('paid_unlinked')
            <h1>Payment received</h1>
            <p class="lede" style="margin:0 auto">Thank you. To see your ticket, use <a href="{{ route('find.show') }}" style="font-weight:600">Find my booking</a> with your reference and the email or phone you booked with (or open the link in your confirmation email).</p>
            @break
        @default
            <h1>We could not find that payment</h1>
            <p class="lede" style="margin:0 auto">If you were charged, use <a href="{{ route('find.show') }}" style="font-weight:600">Find my booking</a> with your reference. Otherwise start again from the home page.</p>
    @endswitch
</div></div></div>
@endsection
