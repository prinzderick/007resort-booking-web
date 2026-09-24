@use('App\Support\Lagos')
@use('App\Support\Money')
@extends('layouts.app')
@section('title', 'Checkout')
@section('noindex', '1')

@section('content')
    @php $live = $secondsLeft > 0 && in_array($booking['status'], ['HELD', 'PENDING_PAYMENT'], true); @endphp
    <div class="page-pad"><div class="wrap">
        <span class="eyebrow">Checkout</span>
        <h1 class="h-1" style="margin-bottom:28px">Almost <i>there.</i></h1>

        <div class="book-layout" style="padding-top:0">
            <section class="panel receipt" aria-labelledby="sum-h">
                <h2 id="sum-h" class="h-3" style="margin-bottom:20px">Your booking</h2>
                <dl>
                    <div><dt>What</dt><dd>{{ $booking['resourceName'] ?? 'Booking' }}</dd></div>
                    <div><dt>Reference</dt><dd>{{ $booking['number'] }}</dd></div>
                    <div><dt>When</dt><dd class="tnum">{{ Lagos::parse($booking['start'])->format('D j M Y') }}, {{ Lagos::parse($booking['start'])->format('H:i') }} - {{ Lagos::parse($booking['end'])->format('H:i') }}</dd></div>
                    <div><dt>Quantity</dt><dd>{{ $booking['quantity'] ?? 1 }}</dd></div>
                </dl>
                <p class="total"><span>Total</span><b>{{ Money::format($booking['total']) }}</b></p>
            </section>

            <aside class="hold-card {{ $live ? '' : 'hold-card--gone' }}">
                @if ($live)
                    <p style="font-weight:600">We are holding your slot for</p>
                    <p class="countdown" data-countdown="{{ $secondsLeft }}" role="timer" aria-live="off">--:--</p>
                    <p class="sr-only" data-countdown-announce aria-live="polite"></p>
                    <p style="font-size:14px;margin-top:6px">Pay before the timer ends or the slot is released to others.</p>

                    <form method="POST" action="{{ route('checkout.pay', $booking['id']) }}" data-once style="margin-top:20px">
                        @csrf
                        <x-idem />
                        <button type="submit" data-busy="Redirecting to Paystack..." class="btn btn--lg btn--block">Pay {{ Money::format($booking['total']) }} with Paystack</button>
                    </form>
                    <form method="POST" action="{{ route('checkout.release', $booking['id']) }}" data-once style="margin-top:10px">
                        @csrf
                        <x-idem />
                        <button type="submit" class="btn btn--line btn--block btn--sm">Release this slot</button>
                    </form>
                @else
                    <p style="font:600 24px var(--serif)">This hold has expired</p>
                    <p style="margin-top:8px">The slot was released so others can book it. Nothing has been charged.</p>
                    <a class="btn" style="margin-top:18px" href="{{ $slug ? route('book.slots', [$slug, $booking['resourceId']]) : route('home') }}">Pick a new time</a>
                @endif
            </aside>
        </div>
    </div></div>
@endsection
