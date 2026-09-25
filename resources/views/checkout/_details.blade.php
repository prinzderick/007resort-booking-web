{{--
    "Your details": name, email, phone. Not an account: no password, no sign-up wording.
    Props: $prefill (name, email, phone, marketing)
--}}
@php
    $consent = config('r007.checkout.consent_version');
    $ids = ['guest_name', 'guest_email', 'guest_phone'];
    $invalid = fn (string $f) => $errors->has($f);
@endphp
<section class="panel co-details" aria-labelledby="det-h" data-guest-card>
    <div class="co-details__head">
        <h2 id="det-h" class="h-3">Your details</h2>
        <p class="hint">Just so we know who to send your ticket to.</p>
    </div>

    <div class="stack">
        <div class="field">
            <label for="guest_name">Full name</label>
            <input id="guest_name" name="guest_name" type="text" value="{{ $prefill['name'] }}" autocomplete="name" autocapitalize="words" enterkeyhint="next" required maxlength="120"
                   @if ($invalid('guest_name')) aria-invalid="true" aria-describedby="guest_name-error" @endif data-guest="name">
            @error('guest_name')<p id="guest_name-error" class="err">{{ $message }}</p>@enderror
        </div>

        <div class="field">
            <label for="guest_email">Email</label>
            <input id="guest_email" name="guest_email" type="email" value="{{ $prefill['email'] }}" autocomplete="email" inputmode="email" autocapitalize="none" spellcheck="false" enterkeyhint="next" required maxlength="190"
                   aria-describedby="guest_email-hint{{ $invalid('guest_email') ? ' guest_email-error' : '' }}" @if ($invalid('guest_email')) aria-invalid="true" @endif data-guest="email">
            <p id="guest_email-hint" class="hint">We&rsquo;ll send your ticket here.</p>
            @error('guest_email')<p id="guest_email-error" class="err">{{ $message }}</p>@enderror
        </div>

        <div class="field">
            <label for="guest_phone">Phone number</label>
            <div class="phone" data-phone>
                <span class="phone__pre" aria-hidden="true"><span class="flag" aria-hidden="true"></span>+234</span>
                <input id="guest_phone" name="guest_phone" type="tel" value="{{ $prefill['phone'] }}" autocomplete="tel" inputmode="tel" enterkeyhint="done" required maxlength="30" placeholder="0803 123 4567"
                       aria-describedby="guest_phone-hint{{ $invalid('guest_phone') ? ' guest_phone-error' : '' }}" @if ($invalid('guest_phone')) aria-invalid="true" @endif data-guest="phone">
            </div>
            <p id="guest_phone-hint" class="hint">Type it the way you normally do, like 0803 123 4567. <span data-phone-preview aria-live="polite"></span></p>
            @error('guest_phone')<p id="guest_phone-error" class="err">{{ $message }}</p>@enderror
        </div>

        <label class="check">
            <input type="checkbox" name="guest_marketing" value="1" @checked($prefill['marketing'])>
            <span>Send me news and offers by email <small>(optional, you can stop any time)</small></span>
        </label>
    </div>

    <p class="co-fine">By paying you agree to our <a href="{{ route('legal.terms') }}" target="_blank" rel="noopener">terms</a> and <a href="{{ route('legal.privacy') }}" target="_blank" rel="noopener">privacy notice</a> (version {{ $consent }}). We only use these details for your booking.
        <button type="button" class="linklike" data-guest-clear hidden>Not you? Clear saved details</button></p>
    @include('checkout._signin')
</section>
