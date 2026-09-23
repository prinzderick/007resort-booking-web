@extends('layouts.app')
@section('title', 'My bookings')
@section('noindex', '1')
@section('content')
    <h1 class="text-3xl font-semibold">My bookings</h1>
    @if ($unavailable)
        <x-notice type="warn" class="mt-6">Your booking history is temporarily unavailable. Please try again in a few minutes.</x-notice>
    @endif
    <ul class="mt-6 space-y-3">
        @forelse ($bookings as $b)
            @include('partials.booking-row', ['b' => $b])
        @empty
            <li class="rounded-xl border border-stone-200 bg-white p-4 text-stone-600">No bookings yet.</li>
        @endforelse
    </ul>
@endsection
