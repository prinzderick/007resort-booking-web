@extends('layouts.app')
@section('title', 'Confirm it is you')
@section('noindex', '1')
@section('content')
<div class="page-pad"><div class="wrap"><div class="auth">
    <span class="eyebrow">Account found</span>
    <h1>Is this <i>you?</i></h1>
    <p class="lede" style="margin-bottom:18px">An account with <strong>{{ $masked }}</strong> already exists. To connect your {{ $provider }} account to it, we sent a code to that address. Enter it below. We never connect accounts without this check.</p>
    <form method="POST" action="{{ route('social.link.store') }}" class="panel stack" data-once>
        @csrf
        <div class="field">
            <label for="code">6-digit code</label>
            <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="12" required autofocus class="code-input" @error('code') aria-invalid="true" aria-describedby="code-error" @enderror>
            <p class="hint">It expires in {{ $minutes }} minutes. Check your spam folder too.</p>
            @error('code')<p id="code-error" class="err">{{ $message }}</p>@enderror
        </div>
        <button type="submit" class="btn btn--lg btn--block" data-busy="Checking…">Connect {{ $provider }}</button>
    </form>
    <div class="inline-actions">
        <form method="POST" action="{{ route('social.link.resend') }}">@csrf<button type="submit" class="link-btn">Send a new code</button></form>
        <span aria-hidden="true">&middot;</span>
        <a href="{{ route('login') }}" class="link-btn">Sign in with my password instead</a>
        <span aria-hidden="true">&middot;</span>
        <form method="POST" action="{{ route('social.cancel') }}">@csrf<button type="submit" class="link-btn">Cancel</button></form>
    </div>
</div></div></div>
@endsection
