@extends('layouts.app')
@section('title', 'Page expired')
@section('noindex', '1')
@section('content')
    <div class="mx-auto max-w-xl py-12 text-center">
        <h1 class="text-2xl font-semibold">That page expired</h1>
        <p class="mt-2 text-stone-600">For your security the form timed out. Nothing was submitted. Please go back, refresh, and try again.</p>
        <a class="mt-6 inline-block rounded bg-emerald-800 px-4 py-2 font-medium text-white" href="{{ url()->previous() }}">Go back</a>
    </div>
@endsection
