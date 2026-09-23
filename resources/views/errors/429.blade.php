@extends('layouts.app')
@section('title', 'Slow down')
@section('noindex', '1')
@section('content')
    <div class="mx-auto max-w-xl py-12 text-center">
        <h1 class="text-2xl font-semibold">Too many requests</h1>
        <p class="mt-2 text-stone-600">Please wait a minute and try again.</p>
    </div>
@endsection
