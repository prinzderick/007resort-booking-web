@extends('layouts.app')
@section('title', 'Finish your profile')
@section('noindex', '1')
@section('content')
<div class="page-pad"><div class="wrap"><div class="auth">
@if ($stage === 'code')
    <span class="eyebrow">Verify your email</span>
    <h1>Check your <i>inbox.</i></h1>
    <p class="lede" style="margin-bottom:18px">We sent a 6-digit code to <strong>{{ \App\Services\Social\SocialFlow::maskEmail($c['pendingEmail'] ?? null) }}</strong>. Enter it to finish.</p>
    <form method="POST" action="{{ route('social.complete.verify') }}" class="panel stack" data-once>
        @csrf
        <div class="field">
            <label for="code">6-digit code</label>
            <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="12" required autofocus class="code-input" @error('code') aria-invalid="true" aria-describedby="code-error" @enderror>
            @error('code')<p id="code-error" class="err">{{ $message }}</p>@enderror
        </div>
        <button type="submit" class="btn btn--lg btn--block" data-busy="Checking…">Verify email</button>
    </form>
    <div class="inline-actions">
        <form method="POST" action="{{ route('social.complete.resend') }}">@csrf<button type="submit" class="link-btn">Send a new code</button></form>
        <span aria-hidden="true">&middot;</span>
        <a class="link-btn" href="{{ route('social.complete', ['change' => 1]) }}">Use a different email</a>
        <span aria-hidden="true">&middot;</span>
        <form method="POST" action="{{ route('social.complete.skip') }}">@csrf<button type="submit" class="link-btn">Skip for now</button></form>
    </div>
@else
    <span class="eyebrow">Nearly done</span>
    <h1>Finish your <i>profile.</i></h1>
    <p class="lede" style="margin-bottom:18px">
        @if ($needEmail) {{ ucfirst($c['provider'] ?? 'Your provider') }} did not share a verified email with us. We need one for your receipts, tickets and bookings.
        @else A phone number helps the front desk reach you about a booking.
        @endif
    </p>
    <form method="POST" action="{{ route('social.complete.store') }}" class="panel stack" data-once novalidate>
        @csrf
        @if ($needName)<x-field name="name" label="Full name" autocomplete="name" :value="$user['name'] ?? null" />@endif
        @if ($needEmail)
            <x-field name="email" label="Email" type="email" autocomplete="email" :value="$c['emailSuggestion'] ?? null" hint="We will email you a 6-digit code to confirm it is yours." />
        @endif
        <x-field name="phone" label="Phone (optional)" type="tel" autocomplete="tel" :required="false" hint="Nigerian mobile, for example 0803 123 4567 or +234 803 123 4567." />
        <button type="submit" class="btn btn--lg btn--block" data-busy="Saving…">{{ $needEmail ? 'Continue' : 'Save' }}</button>
    </form>
    @if ($needEmail)
        <p class="hint" style="font-size:13.5px;color:var(--mute);margin-top:14px">Already have a 007 account with this email? Skip this step, sign out, sign in with your password and connect {{ ucfirst($c['provider'] ?? 'the provider') }} from <em>Sign-in methods</em> in your account.</p>
    @endif
    <div class="inline-actions">
        <form method="POST" action="{{ route('social.complete.skip') }}">@csrf<button type="submit" class="link-btn">Skip for now</button></form>
    </div>
@endif
</div></div></div>
@endsection
