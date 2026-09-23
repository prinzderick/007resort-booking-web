@extends('layouts.app')
@section('title', 'Page not found')
@section('noindex', '1')
@section('content')
    <div class="mx-auto max-w-xl py-12 text-center">
        <h1 class="text-2xl font-semibold">We could not find that page</h1>
        <p class="mt-2 text-stone-600">The link may be old, or the booking may belong to a different account.</p>
        <a class="mt-6 inline-block rounded bg-emerald-800 px-4 py-2 font-medium text-white" href="{{ route('home') }}">Back to home</a>
    </div>
@endsection
