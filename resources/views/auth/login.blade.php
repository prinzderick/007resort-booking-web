@extends('layouts.app')
@section('title', 'Sign in')
@section('noindex', '1')
@section('content')
<div class="page-pad"><div class="wrap"><div class="auth">
    <span class="eyebrow">Welcome back</span>
    <h1>Sign <i>in.</i></h1>
    <form method="POST" action="{{ route('login.store') }}" class="panel stack" data-once>
        @csrf
        <x-field name="email" label="Email" type="email" autocomplete="email" />
        <x-field name="password" label="Password" type="password" autocomplete="current-password" />
        <button type="submit" class="btn btn--lg btn--block">Sign in</button>
    </form>
    <p style="margin-top:18px;color:var(--mute)">New here? <a href="{{ route('register') }}" style="font-weight:600;color:var(--ink)">Create an account</a></p>
</div></div></div>
@endsection
