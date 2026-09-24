@use('App\Support\Lagos')
@extends('layouts.app')
@section('title', count($tickets) > 1 ? 'Your tickets' : 'Your ticket')
@section('noindex', '1')

@section('content')
<div class="page-pad"><div class="wrap">
    <div class="done-hero">
        <div class="check"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></div>
        <h1 class="h-1">{{ count($tickets) > 1 ? 'Your tickets ('.count($tickets).')' : 'Your ticket' }}</h1>
        <p class="lede" style="margin:12px auto 0">Show the QR code at the entrance. Keep this page, save the image, or print it. Screenshots work too.</p>
        <p class="no-print" style="margin-top:22px;display:flex;gap:10px;justify-content:center;flex-wrap:wrap">
            <button type="button" data-print class="btn btn--line btn--sm">Print</button>
            <a class="btn btn--line btn--sm" href="{{ route('account.bookings') }}">My bookings</a>
        </p>
    </div>

    <div class="tickets">
        @foreach ($tickets as $t)
            @php $item = $t['items'][0] ?? null; @endphp
            <article class="tkt reveal" style="--i:{{ $loop->index }}">
                <div class="tkt-top">
                    <span class="eyebrow">007 Resort &amp; Spa</span>
                    <h2>{{ $item['name'] ?? 'Ticket' }}</h2>
                </div>
                <div class="tkt-mid">
                    <img src="{{ route('tickets.qr', $t['id']) }}" alt="QR code for {{ $item['name'] ?? 'ticket' }}" width="260" height="260">
                    <dl>
                        @if (! empty($t['holderName']))<div><dt>Name: </dt><dd>{{ $t['holderName'] }}</dd></div>@endif
                        @if ($item && $item['validFrom'])<div><dt>Valid: </dt><dd class="tnum">{{ Lagos::parse($item['validFrom'])->format('D j M, H:i') }} &ndash; {{ Lagos::parse($item['validUntil'])?->format('D j M, H:i') }}</dd></div>@endif
                        <div><dt>Status: </dt><dd class="status-dot">{{ ucfirst(strtolower($t['status'])) }}</dd></div>
                        <div class="code">Ticket {{ strtoupper(substr($t['id'], -8)) }}</div>
                    </dl>
                    <p class="no-print" style="margin-top:14px"><a style="font-size:14px;font-weight:600" href="{{ route('tickets.download', $t['id']) }}">Download QR image (SVG)</a></p>
                </div>
            </article>
        @endforeach
    </div>
</div></div>
@endsection
