@extends('layouts.app')
@section('title', 'Verify your email')
@section('noindex', '1')
@section('content')
    <div class="mx-auto max-w-md">
        <h1 class="text-2xl font-semibold">Verify your email</h1>
        <p class="mt-1 text-sm text-stone-600">Enter the code we sent you.@if (config('r007.mock')) <strong>(Mock mode: the code is 123456.)</strong>@endif</p>
        <form method="POST" action="{{ route('verify.store') }}" class="mt-6 space-y-4 rounded-xl border border-stone-200 bg-white p-6" data-once>
            @csrf
            <x-field name="email" label="Email" type="email" :value="$email" autocomplete="email" />
            <x-field name="code" label="Verification code" autocomplete="one-time-code" />
            <button type="submit" class="w-full rounded-lg bg-emerald-800 px-4 py-3 font-semibold text-white hover:bg-emerald-900 disabled:opacity-60">Verify</button>
        </form>
        <form method="POST" action="{{ route('verify.resend') }}" class="mt-3">
            @csrf
            <input type="hidden" name="email" value="{{ $email }}">
            <button class="text-sm text-emerald-800 underline">Send a new code</button>
        </form>
    </div>
@endsection
