@extends('layouts.app')
@section('title', 'Find my booking')
@section('description', 'Look up your 007 Resort & Spa booking or tickets with your reference and email or phone.')
@section('noindex', '1')
@section('no_cta', '1')

@push('head')
    @if ($turnstileKey)<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer nonce="{{ $cspNonce ?? '' }}"></script>@endif
@endpush

@section('content')
<div class="page-pad"><div class="wrap"><div class="auth">
    <span class="eyebrow">Your booking</span>
    <h1>Find my <i>booking.</i></h1>
    <p class="lede" style="margin-bottom:22px">Enter the reference from your confirmation and the email or phone number you booked with. No account needed.</p>

    @if ($errors->has('form'))<x-notice type="error">{{ $errors->first('form') }}</x-notice>@endif

    <form method="POST" action="{{ route('find.lookup') }}" class="panel stack" data-once novalidate>
        @csrf
        <x-idem />
        <div class="field">
            <label for="reference">Booking reference</label>
            <input id="reference" name="reference" type="text" value="{{ old('reference', $reference) }}" autocapitalize="characters" autocomplete="off" spellcheck="false" enterkeyhint="next" required maxlength="60" placeholder="007-ABC1234" aria-describedby="reference-hint @error('reference') reference-error @enderror" @error('reference') aria-invalid="true" @enderror>
            <p id="reference-hint" class="hint">It is on your confirmation page and in your email.</p>
            @error('reference')<p id="reference-error" class="err">{{ $message }}</p>@enderror
        </div>
        <div class="field">
            <label for="contact">Email or phone number</label>
            <input id="contact" name="contact" type="text" value="{{ old('contact') }}" autocomplete="email" autocapitalize="none" spellcheck="false" enterkeyhint="go" required maxlength="190" @error('contact') aria-invalid="true" aria-describedby="contact-error" @enderror>
            @error('contact')<p id="contact-error" class="err">{{ $message }}</p>@enderror
        </div>
        @if ($turnstileKey)<div class="cf-turnstile" data-sitekey="{{ $turnstileKey }}"></div>@endif
        <button type="submit" class="btn btn--lg btn--block" data-busy="Looking...">Find my booking</button>
    </form>

    @if (count($recent))
        <section class="recent" aria-labelledby="recent-h">
            <h2 id="recent-h" class="step-label" style="margin-top:28px">Opened on this device</h2>
            <ul class="stack">@foreach ($recent as $ref)<li><a class="row-item" href="{{ route('orders.show', $ref) }}"><span class="tnum" style="font-weight:600">{{ $ref }}</span><span aria-hidden="true">&rarr;</span></a></li>@endforeach</ul>
        </section>
    @endif
    <p style="margin-top:22px;color:var(--mute)">Have an account? <a href="{{ route('login') }}" style="font-weight:600;color:var(--ink)">Sign in</a> to see all your bookings.</p>
</div></div></div>
@endsection
