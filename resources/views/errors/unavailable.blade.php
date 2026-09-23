@extends('layouts.app')
@section('title', 'Temporarily unavailable')
@section('noindex', '1')
@section('content')
    <div class="mx-auto max-w-xl rounded-xl border border-amber-200 bg-amber-50 p-6 text-amber-900">
        <h1 class="text-xl font-semibold">This part of the site is taking a short break</h1>
        <p class="mt-2">We could not reach our booking system just now. Nothing has been charged. You can browse the rest of the site, try again in a few minutes, or contact reception.</p>
        <p class="mt-4 flex gap-3 text-sm"><a class="rounded bg-emerald-800 px-3 py-2 font-medium text-white" href="{{ route('home') }}">Back to home</a><a class="rounded border border-amber-400 px-3 py-2" href="{{ route('contact') }}">Contact us</a></p>
    </div>
@endsection
