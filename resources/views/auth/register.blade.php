@extends('layouts.app')
@section('title', 'Create an account')
@section('noindex', '1')
@section('content')
<div class="page-pad"><div class="wrap"><div class="auth">
    <span class="eyebrow">Join in</span>
    <h1>Create your <i>account.</i></h1>
    <p class="lede" style="margin-bottom:20px">Needed to hold slots, pay online and see your bookings and QR tickets.</p>
    <div class="panel stack">
    <x-social-buttons from="register" />
    <form method="POST" action="{{ route('register.store') }}" class="stack" data-once>
        @csrf
        <x-spam />
        <x-idem />
        <x-field name="name" label="Full name" autocomplete="name" />
        <x-field name="email" label="Email" type="email" autocomplete="email" />
        <x-field name="phone" label="Phone" type="tel" autocomplete="tel" hint="For example +2348012345678" />
        <x-field name="password" label="Password" type="password" autocomplete="new-password" hint="At least 10 characters." />
        <x-field name="password_confirmation" label="Confirm password" type="password" autocomplete="new-password" />
        <button type="submit" class="btn btn--lg btn--block">Create account</button>
        <p class="hint" style="font-size:13px;color:var(--mute)">We only use your details to run your bookings. We do not sell them.</p>
    </form>
    </div>
    <p style="margin-top:18px;color:var(--mute)">Already registered? <a href="{{ route('login') }}" style="font-weight:600;color:var(--ink)">Sign in</a></p>
</div></div></div>
@endsection
