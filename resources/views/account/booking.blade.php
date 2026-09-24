@use('App\Support\Lagos')
@use('App\Support\Money')
@extends('layouts.app')
@section('title', 'Booking '.$booking['number'])
@section('noindex', '1')
@section('content')
<div class="page-pad"><div class="wrap">
    <nav class="crumbs" aria-label="Breadcrumb" style="color:var(--mute)"><a href="{{ route('account.bookings') }}">My bookings</a><span aria-hidden="true">/</span><span aria-current="page">{{ $booking['number'] }}</span></nav>
    <h1 class="h-1" style="margin:8px 0 26px">{{ $booking['resourceName'] ?? 'Booking' }}</h1>

    <div class="book-layout" style="padding-top:0">
        <section class="panel receipt">
            <dl>
                <div><dt>Status</dt><dd>{{ ucfirst(strtolower(str_replace('_', ' ', $booking['status']))) }}</dd></div>
                <div><dt>Reference</dt><dd>{{ $booking['number'] }}</dd></div>
                <div><dt>When</dt><dd class="tnum">{{ Lagos::parse($booking['start'])->format('D j M Y, H:i') }} - {{ Lagos::parse($booking['end'])->format('H:i') }}</dd></div>
                <div><dt>Total</dt><dd>{{ Money::format($booking['total']) }} (paid {{ Money::format($booking['amountPaid']) }})</dd></div>
            </dl>

            @if ($entitlement)
                <div style="margin-top:26px;border-top:1px dashed var(--line);padding-top:26px;text-align:center">
                    <img src="{{ route('tickets.qr', $entitlement['id']) }}" alt="QR ticket" width="200" height="200" style="margin-inline:auto;background:#fff;padding:10px;border-radius:14px">
                    <p style="margin-top:14px"><a class="btn btn--sm" href="{{ route('tickets.show', $entitlement['id']) }}">Open ticket</a></p>
                </div>
            @endif
        </section>

        <aside class="stack">
            @if (in_array($booking['status'], ['HELD', 'PENDING_PAYMENT'], true))
                <a class="btn btn--lg btn--block" href="{{ route('checkout.show', $booking['id']) }}">Complete payment</a>
            @endif

            @if ($rules['note'] || $rules['cancelBy'])
                <div class="panel" style="font-size:15px">
                    <p style="font-weight:600">Change &amp; cancellation</p>
                    @if ($rules['note'])<p style="margin-top:6px">{{ $rules['note'] }}</p>@endif
                    @if ($rules['cancelBy'])<p style="margin-top:6px">Free changes until {{ Lagos::parse($rules['cancelBy'])->format('D j M, H:i') }}.</p>@endif
                    @if ($rules['refundAmount'] !== null)<p style="margin-top:6px">Refund if cancelled now: <strong>{{ Money::format($rules['refundAmount']) }}</strong></p>@endif
                </div>
            @endif

            @if ($rules['canReschedule'])
                <a class="btn btn--line btn--block" href="{{ route('account.bookings.reschedule', $booking['id']) }}">Reschedule</a>
            @endif
            @if ($rules['canCancel'])
                <form method="POST" action="{{ route('account.bookings.cancel', $booking['id']) }}" data-once data-confirm="Cancel this booking?" class="panel stack">
                    @csrf
                    <x-idem />
                    <div class="field"><label for="reason">Reason (optional)</label><input id="reason" name="reason" maxlength="300"></div>
                    <button class="btn btn--line btn--block" style="--bd:var(--bad);--fg:var(--bad)">Cancel booking</button>
                </form>
            @elseif ($booking['status'] === 'CONFIRMED')
                <p class="panel panel--line" style="font-size:15px;color:var(--mute)">This booking can no longer be changed online. Please contact reception.</p>
            @endif
        </aside>
    </div>
</div></div>
@endsection
