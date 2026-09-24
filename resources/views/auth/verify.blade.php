@extends('layouts.app')
@section('title', 'Verify your email')
@section('noindex', '1')
@section('content')
<div class="page-pad"><div class="wrap"><div class="auth">
    <span class="eyebrow">One more step</span>
    <h1>Verify your <i>email.</i></h1>
    <p class="lede" style="margin-bottom:20px">Enter the code we sent you.@if (config('r007.mock')) <strong>(Mock mode: the code is 123456.)</strong>@endif</p>
    <form method="POST" action="{{ route('verify.store') }}" class="panel stack" data-once>
        @csrf
        <x-field name="email" label="Email" type="email" :value="$email" autocomplete="email" />
        <x-field name="code" label="Verification code" autocomplete="one-time-code" />
        <button type="submit" class="btn btn--lg btn--block">Verify</button>
    </form>
    <form method="POST" action="{{ route('verify.resend') }}" style="margin-top:14px">
        @csrf
        <input type="hidden" name="email" value="{{ $email }}">
        <button class="btn btn--line btn--sm">Send a new code</button>
    </form>
</div></div></div>
@endsection
