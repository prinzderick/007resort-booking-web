@use('App\Support\Lagos')
@extends('layouts.app')
@section('title', 'Reschedule')
@section('noindex', '1')
@section('content')
    <nav class="text-sm text-stone-500" aria-label="Breadcrumb"><a class="hover:underline" href="{{ route('account.bookings.show', $booking['id']) }}">{{ $booking['number'] }}</a> / Reschedule</nav>
    <h1 class="mt-2 text-3xl font-semibold">Reschedule {{ $booking['resourceName'] ?? '' }}</h1>
    <p class="mt-1 text-stone-600">Currently {{ Lagos::parse($booking['start'])->format('D j M Y, H:i') }}. Pick a new time; your current slot is kept until the new one is secured.</p>
    @if ($notice)
        <x-notice type="warn" class="mt-6">{{ $notice }}</x-notice>
    @else
        <div class="mt-6">
            @include('partials.day-picker', ['days' => $days, 'day' => $day, 'url' => fn ($d) => route('account.bookings.reschedule', [$booking['id'], 'date' => $d])])
        </div>
        <div class="mt-6">
            @include('partials.slot-grid', ['slots' => $slots, 'action' => route('account.bookings.reschedule.store', $booking['id']), 'resource' => null])
        </div>
    @endif
@endsection
