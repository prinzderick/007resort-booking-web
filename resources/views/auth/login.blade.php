@extends('layouts.app')
@section('title', 'Sign in')
@section('noindex', '1')
@section('content')
    <div class="mx-auto max-w-md">
        <h1 class="text-2xl font-semibold">Sign in</h1>
        <form method="POST" action="{{ route('login.store') }}" class="mt-6 space-y-4 rounded-xl border border-stone-200 bg-white p-6" data-once>
            @csrf
            <x-field name="email" label="Email" type="email" autocomplete="email" />
            <x-field name="password" label="Password" type="password" autocomplete="current-password" />
            <button type="submit" class="w-full rounded-lg bg-emerald-800 px-4 py-3 font-semibold text-white hover:bg-emerald-900 disabled:opacity-60">Sign in</button>
        </form>
        <p class="mt-4 text-sm text-stone-600">New here? <a class="text-emerald-800 underline" href="{{ route('register') }}">Create an account</a></p>
    </div>
@endsection
