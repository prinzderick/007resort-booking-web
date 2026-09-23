@extends('layouts.app')
@section('title', 'Create an account')
@section('noindex', '1')
@section('content')
    <div class="mx-auto max-w-md">
        <h1 class="text-2xl font-semibold">Create your account</h1>
        <p class="mt-1 text-sm text-stone-600">Needed to hold slots, pay online and see your bookings and QR tickets.</p>
        <form method="POST" action="{{ route('register.store') }}" class="mt-6 space-y-4 rounded-xl border border-stone-200 bg-white p-6" data-once>
            @csrf
            <x-spam />
            <x-idem />
            <x-field name="name" label="Full name" autocomplete="name" />
            <x-field name="email" label="Email" type="email" autocomplete="email" />
            <x-field name="phone" label="Phone" type="tel" autocomplete="tel" hint="For example +2348012345678" />
            <x-field name="password" label="Password" type="password" autocomplete="new-password" hint="At least 10 characters." />
            <x-field name="password_confirmation" label="Confirm password" type="password" autocomplete="new-password" />
            <button type="submit" class="w-full rounded-lg bg-emerald-800 px-4 py-3 font-semibold text-white hover:bg-emerald-900 disabled:opacity-60">Create account</button>
            <p class="text-xs text-stone-500">We only use your details to run your bookings. We do not sell them.</p>
        </form>
        <p class="mt-4 text-sm text-stone-600">Already registered? <a class="text-emerald-800 underline" href="{{ route('login') }}">Sign in</a></p>
    </div>
@endsection
