@extends('layouts.app')
@section('title', 'One last step')
@section('noindex', '1')
@section('content')
<div class="page-pad"><div class="wrap"><div class="auth">
    <span class="eyebrow">Almost there</span>
    <h1>Create your <i>account.</i></h1>
    <div class="who">
        <x-avatar :url="$identity->avatarUrl" :name="$identity->name" :size="52" />
        <div>
            <p class="who__name">{{ $identity->name ?: 'Welcome' }}</p>
            <p class="who__sub">{{ $identity->email ?: 'Signed in with '.$provider }}</p>
        </div>
    </div>
    <p class="lede" style="margin:14px 0 18px">We have not seen you here before, so we will create a 007 Resort &amp; Spa account from your {{ $provider }} details. Please confirm two things.</p>
    <form method="POST" action="{{ route('social.consent.store') }}" class="panel stack" data-once>
        @csrf
        <label class="check">
            <input type="checkbox" name="terms" value="1" required @checked(old('terms')) @error('terms') aria-invalid="true" aria-describedby="terms-error" @enderror>
            <span>I agree to the <a href="{{ route('legal.terms') }}" target="_blank" rel="noopener">Terms</a> and have read the <a href="{{ route('legal.privacy') }}" target="_blank" rel="noopener">Privacy Policy</a>.</span>
        </label>
        @error('terms')<p id="terms-error" class="err">{{ $message }}</p>@enderror
        <label class="check">
            <input type="checkbox" name="marketing" value="1" @checked(old('marketing'))>
            <span>Send me news, events and offers by email. <em>Optional. You can unsubscribe any time.</em></span>
        </label>
        <button type="submit" class="btn btn--lg btn--block" data-busy="Creating your account…">Create my account</button>
        <p class="hint" style="font-size:13px;color:var(--mute)">We only receive your name, email and profile photo from {{ $provider }}. We never post anything or see your {{ $provider }} password.</p>
    </form>
    <form method="POST" action="{{ route('social.cancel') }}" style="margin-top:14px">@csrf<button type="submit" class="link-btn">No thanks, take me back</button></form>
</div></div></div>
@endsection
