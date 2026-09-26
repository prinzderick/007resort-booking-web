@use('App\Support\Lagos')
@use('App\Support\Money')
@extends('layouts.app')
@section('title', $state === 'paid' ? 'Your ticket' : 'Your booking')
@section('noindex', '1')
@section('no_cta', '1')

@push('head')
    @if ($state === 'issuing')<meta http-equiv="refresh" content="4">@endif
@endpush

@php
    $kind = $order['kind'] ?? 'tickets';
    $many = count($tickets) > 1;
    $policy = (array) ($order['policy'] ?? []);
    $niceStatus = ucfirst(strtolower(str_replace('_', ' ', (string) $order['status'])));
    $whenLine = null;
    if ($kind === 'booking' && ! empty($order['start'])) {
        $s = Lagos::parse($order['start']); $e = Lagos::parse($order['end']);
        $whenLine = $s->format('l j F Y').', '.$s->format('H:i').' - '.$e->format('H:i');
    } elseif (! empty($order['visitDate'])) {
        $whenLine = Lagos::dayStart(substr((string) $order['visitDate'], 0, 10))?->format('l j F Y');
    }
    $canCancel = $kind === 'booking' && ! empty($policy['canCancel']);
@endphp

@section('content')
<div class="page-pad"><div class="wrap">

@if ($state === 'paid')
    <div class="done-hero">
        <div class="check"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></div>
        <h1 class="h-1" tabindex="-1" data-focus-me>{{ match ($kind) { 'membership' => 'Welcome, member.', 'booking' => "You're booked.", default => $many ? 'Your tickets are ready.' : 'Your ticket is ready.' } }}</h1>
        <p class="lede" style="margin:12px auto 0">{{ $order['title'] ?? '' }}@if ($whenLine)<br><span class="tnum">{{ $whenLine }}</span>@endif</p>

        <p class="delivery" role="status">
            @if ($delivery['sent'] && $email !== '')
                We&rsquo;ve sent your ticket to <b>{{ $emailMasked }}</b>.
            @elseif ($delivery['available'] && $email !== '')
                We&rsquo;re sending your ticket to <b>{{ $emailMasked }}</b>. If it does not arrive in a few minutes, tap &ldquo;Send it again&rdquo;.
            @else
                Keep this page or use the link below. We can&rsquo;t send emails right now, so please save your ticket.
            @endif
        </p>

        <div class="actions no-print">
            <button type="button" data-print class="btn btn--sm">Save ticket (print or PDF)</button>
            @if ($canCalendar)<a class="btn btn--line btn--sm" href="{{ route('orders.ics', $order['reference']) }}">Add to calendar</a>@endif
            <button type="button" class="btn btn--line btn--sm" data-share data-title="{{ $order['title'] ?? 'My visit' }} at 007 Resort &amp; Spa" data-url="{{ route('home') }}" hidden>Share</button>
            @if ($delivery['available'])
                <form method="POST" action="{{ route('orders.resend', $order['reference']) }}" data-once style="display:inline">@csrf<x-idem /><button class="btn btn--line btn--sm" data-busy="Sending...">Send it again</button></form>
            @endif
        </div>
    </div>

    <div class="tickets">
        @foreach ($tickets as $t)
            @include('orders._ticket', ['t' => $t, 'order' => $order, 'i' => $loop->index, 'n' => count($tickets)])
        @endforeach
    </div>

    <div class="order-grid no-print">
        <section class="panel" aria-labelledby="keep-h">
            <h2 id="keep-h" class="h-3">Your private link</h2>
            <p style="margin:8px 0 14px;font-size:15px;color:var(--ink-2)">Open your ticket again from any device with this link. Anyone who has it can see your ticket, so keep it to yourself.</p>
            <div class="copyrow">
                <input type="text" readonly value="{{ $manageUrl }}" aria-label="Link to this booking" data-copy-src onfocus="this.select()">
                <button type="button" class="btn btn--sm" data-copy hidden>Copy link</button>
            </div>
            <p class="hint" style="margin-top:10px">Lost it? <a href="{{ route('find.show', ['reference' => $order['reference']]) }}">Find my booking</a> with your reference and email.</p>
        </section>

        <section class="panel receipt" aria-labelledby="det-h">
            <h2 id="det-h" class="h-3" style="margin-bottom:16px">Booking details</h2>
            <dl>
                <div><dt>Reference</dt><dd class="tnum">{{ $order['reference'] }}</dd></div>
                <div><dt>Status</dt><dd>{{ $niceStatus }}</dd></div>
                @if ($whenLine)<div><dt>When</dt><dd class="tnum">{{ $whenLine }}</dd></div>@endif
                <div><dt>Total paid</dt><dd>{{ Money::format($order['total']) }}</dd></div>
                @if (! empty($order['guest']['name']))<div><dt>Booked by</dt><dd>{{ $order['guest']['name'] }}</dd></div>@endif
            </dl>
            @if ($kind === 'booking')
                <div class="policy">
                    <p style="font-weight:600">Changes and cancellation</p>
                    <p>{{ $policy['note'] ?? 'Ask us if you need to change or cancel.' }}</p>
                    @if (! empty($policy['cancelBy']))<p>Free cancellation until {{ Lagos::parse($policy['cancelBy'])->format('D j M, H:i') }}.@if (isset($policy['refundAmount'])) Refund if cancelled now: <b>{{ Money::format($policy['refundAmount']) }}</b>.@endif</p>@endif
                    @if ($canCancel)
                        <form method="POST" action="{{ route('orders.cancel', $order['reference']) }}" data-once data-confirm="Cancel this booking?" class="stack" style="margin-top:14px">
                            @csrf<x-idem />
                            <div class="field"><label for="reason">Reason (optional)</label><input id="reason" name="reason" maxlength="300"></div>
                            <button class="btn btn--line btn--sm" style="--bd:var(--bad);--fg:var(--bad)">Cancel this booking</button>
                        </form>
                    @else
                        <p style="color:var(--mute)">This booking can no longer be cancelled online. <a href="{{ route('contact') }}">Contact reception</a> if you need help. To change the time, cancel and book a new slot, or ask us.</p>
                    @endif
                </div>
            @else
                <div class="policy"><p style="color:var(--mute)">Need to change something? <a href="{{ route('contact') }}">Contact us</a> with your reference.</p></div>
            @endif
        </section>
    </div>

    @if ($justPaid && ! $signedIn)
        <section class="panel acct-prompt no-print" data-acct-prompt aria-labelledby="acct-h">
            <button type="button" class="acct-prompt__x" aria-label="Dismiss" data-acct-dismiss hidden>&times;</button>
            <h2 id="acct-h" class="h-3">Want all your bookings in one place?</h2>
            <p>Keep this and future bookings together on any device, with your same details. Choose a password and you&rsquo;re done.</p>
            @php $acctErr = $errors->getBag('account'); @endphp
            <form method="POST" action="{{ route('orders.account', $order['reference']) }}" data-once class="acct-prompt__form">
                @csrf<x-idem />
                <div class="field">
                    <label for="acct-pw">Choose a password</label>
                    <input id="acct-pw" name="password" type="password" autocomplete="new-password" minlength="10" required @if ($acctErr->has('password')) aria-invalid="true" aria-describedby="acct-pw-err" @endif>
                    <p class="hint">At least 10 characters.</p>
                    @if ($acctErr->has('password'))<p id="acct-pw-err" class="err">{{ $acctErr->first('password') }}</p>@endif
                </div>
                <button type="submit" class="btn" data-busy="One moment...">Save my bookings</button>
            </form>
            @stack('checkout-social-post')
        </section>
    @endif

@elseif ($state === 'issuing')
    <div class="pay-result panel">
        <div class="spinner" aria-hidden="true"></div>
        <h1>Confirming your payment...</h1>
        <p class="lede" style="margin:0 auto">Your payment went through. We are preparing your ticket. This page refreshes on its own. Please do not pay again.</p>
        <p style="margin-top:16px;color:var(--mute);font-size:14px">Reference <b class="tnum">{{ $order['reference'] }}</b></p>
    </div>

@elseif ($state === 'pending_payment')
    <div class="pay-result panel">
        <h1 tabindex="-1" data-focus-me>Payment not finished</h1>
        <p class="lede" style="margin:0 auto">{{ $order['title'] ?? 'Your order' }}@if ($whenLine) - <span class="tnum">{{ $whenLine }}</span>@endif. Nothing has been charged yet, and your order is safe.</p>
        @if (! empty($order['holdExpiresAt']) && $secondsLeft <= 0)
            <div class="hold-card hold-card--gone" style="margin-top:22px;text-align:left">
                <p style="font:600 22px var(--serif)">The slot hold has ended</p>
                <p style="margin-top:6px">It went back on sale so others can book it. Nothing was charged.</p>
                <a class="btn" style="margin-top:14px" href="{{ $slug ? route('book.resources', $slug) : route('home') }}">Pick a new time</a>
            </div>
        @else
            @if (! empty($order['holdExpiresAt']))
                <p style="margin-top:18px">We are still holding your slot for <b class="countdown" style="font-size:28px" data-countdown="{{ $secondsLeft }}" role="timer" aria-live="off">--:--</b></p>
            @endif
            <form method="POST" action="{{ route('orders.pay', $order['reference']) }}" data-once style="margin-top:22px">
                @csrf<x-idem />
                <button type="submit" class="btn btn--lg" data-busy="Taking you to Paystack...">Try paying again {{ Money::format($order['total']) }}</button>
            </form>
        @endif
        <p style="margin-top:16px;color:var(--mute);font-size:14px">Reference <b class="tnum">{{ $order['reference'] }}</b></p>
    </div>

@elseif ($state === 'paid_hold_lost')
    <div class="pay-result panel">
        <h1>We received your payment but the slot was lost</h1>
        <p class="lede" style="margin:0 auto">Your hold ended before payment finished and the slot is no longer yours. Our team will refund you. Please contact us with reference <b>{{ $order['reference'] }}</b> if you do not hear back.</p>
        <a class="btn btn--line" style="margin-top:22px" href="{{ route('contact') }}">Contact us</a>
    </div>

@else
    <div class="pay-result panel">
        <h1 tabindex="-1" data-focus-me>{{ match ($state) { 'cancelled' => 'This booking was cancelled', 'expired' => 'This order has expired', 'refunded' => 'This order was refunded', default => 'Booking '.strtolower($niceStatus) } }}</h1>
        <p class="lede" style="margin:0 auto">{{ $order['title'] ?? '' }}@if ($whenLine) - <span class="tnum">{{ $whenLine }}</span>@endif</p>
        <p style="margin-top:16px;color:var(--mute);font-size:14px">Reference <b class="tnum">{{ $order['reference'] }}</b>. Any refund follows the cancellation policy and goes back to your original payment method.</p>
        <a class="btn" style="margin-top:22px" href="{{ $slug ? route('book.resources', $slug) : route('home') }}">Book again</a>
    </div>
@endif

</div></div>
@endsection
