{{--
    One checkout screen for court/slot bookings, pool tickets and memberships.
    Props: $action, $heading, $lines [[label, value]...], $total (formatted), $payLabel, $secondsLeft (?int; null = no hold),
           $account (?array: signed-in profile -> details card skipped), $prefill (guest card values), $release (?url), $footnote (?string)
--}}
@php
    $live = $secondsLeft === null || $secondsLeft > 0;
    $fieldErrors = collect(['guest_name', 'guest_email', 'guest_phone'])->filter(fn ($f) => $errors->has($f));
@endphp
<div class="page-pad co"><div class="wrap">
    <span class="eyebrow">Checkout</span>
    <h1 class="h-1" style="margin-bottom:28px">{!! $heading !!}</h1>

    @if ($fieldErrors->isNotEmpty())
        <div class="notice notice--error co-errors" role="alert" tabindex="-1" data-error-summary aria-labelledby="err-h">
            <div><p id="err-h" style="font-weight:600">Please fix {{ $fieldErrors->count() === 1 ? 'this' : 'these' }} to continue</p>
                <ul>@foreach ($fieldErrors as $f)<li><a href="#{{ $f }}">{{ $errors->first($f) }}</a></li>@endforeach</ul></div>
        </div>
    @endif

    @if ($live)
        <form method="POST" action="{{ $action }}" class="book-layout co-layout" style="padding-top:0" data-once data-guest-form @if ($account) data-account @endif>
            @csrf
            <x-idem />
            <div class="stack-lg">
                <section class="panel receipt" aria-labelledby="sum-h">
                    <h2 id="sum-h" class="h-3" style="margin-bottom:20px">{{ $summaryTitle ?? 'Your booking' }}</h2>
                    <dl>@foreach ($lines as [$label, $value])<div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>@endforeach</dl>
                    <p class="total"><span>Total</span><b>{{ $total }}</b></p>
                </section>

                @if ($account)
                    <section class="panel co-as" aria-label="Your details">
                        <p>Booking as <b>{{ $account['name'] }}</b><br><span class="hint">{{ $account['email'] }} &middot; your ticket goes to your account</span></p>
                    </section>
                @else
                    @include('checkout._details', ['prefill' => $prefill])
                @endif
            </div>

            <aside class="book-side co-aside" aria-label="Payment">
                @if ($secondsLeft !== null)
                    <div class="hold-card">
                        <p style="font-weight:600">We are holding your slot for</p>
                        <p class="countdown" data-countdown="{{ $secondsLeft }}" role="timer" aria-live="off">--:--</p>
                        <p class="sr-only" data-countdown-announce aria-live="polite"></p>
                        <p style="font-size:14px;margin-top:6px">Pay before the timer ends or the slot goes back on sale.</p>
                    </div>
                @endif
                <div class="co-pay">
                    <button type="submit" data-busy="Taking you to Paystack..." class="btn btn--lg btn--block">{{ $payLabel }}</button>
                    <p class="co-secure"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>Secure card, bank transfer or USSD payment by Paystack.</p>
                    @if (! empty($footnote))<p class="co-secure">{{ $footnote }}</p>@endif
                    @if (! empty($release))<button type="submit" form="release-form" class="btn btn--line btn--block btn--sm" style="margin-top:12px">Release this slot</button>@endif
                </div>
            </aside>

            <div class="paybar" data-paybar>
                <div class="paybar__sum"><small>Total</small><b>{{ $total }}</b></div>
                <button type="submit" data-busy="One moment..." class="btn btn--lg">{{ $payLabel }}</button>
            </div>
        </form>
        @if (! empty($release))
            <form id="release-form" method="POST" action="{{ $release }}" data-once>@csrf<x-idem /></form>
        @endif
    @else
        <div class="hold-card hold-card--gone co-gone" role="alert">
            <p style="font:600 26px var(--serif)">Your hold has ended</p>
            <p style="margin-top:8px">No problem, nothing has been charged. The slot went back on sale so others can book it. Your details are saved on this device, so booking again takes a few seconds.</p>
            <a class="btn" style="margin-top:18px" href="{{ $recoverUrl }}">Pick a new time</a>
        </div>
    @endif
</div></div>
