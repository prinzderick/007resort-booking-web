@extends('layouts.app')
@section('title', 'Something went wrong')
@section('noindex', '1')
@section('content')
    <div class="mx-auto max-w-xl rounded-xl border border-red-200 bg-red-50 p-6 text-red-900">
        <h1 class="text-xl font-semibold">We could not complete that</h1>
        <p class="mt-2">{{ $message }}</p>
        <p class="mt-4 text-sm"><a class="rounded bg-emerald-800 px-3 py-2 font-medium text-white" href="{{ url()->previous() }}">Go back</a></p>
    </div>
@endsection
