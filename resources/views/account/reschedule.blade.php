@use('App\Support\Lagos')
@extends('layouts.app')
@section('title', 'Reschedule')
@section('noindex', '1')
@section('content')
<div class="page-pad"><div class="wrap">
    <nav class="crumbs" aria-label="Breadcrumb" style="color:var(--mute)"><a href="{{ route('account.bookings.show', $booking['id']) }}">{{ $booking['number'] }}</a><span aria-hidden="true">/</span><span aria-current="page">Reschedule</span></nav>
    <h1 class="h-1" style="margin:8px 0 10px">Reschedule <i>{{ $booking['resourceName'] ?? '' }}</i></h1>
    <p class="lede">Currently {{ Lagos::parse($booking['start'])->format('D j M Y, H:i') }}. Pick a new time; your current slot is kept until the new one is secured.</p>
    @if ($notice)
        <x-notice type="warn" style="margin-top:24px">{{ $notice }}</x-notice>
    @else
        <div style="margin-top:24px">@include('partials.day-picker', ['days' => $days, 'day' => $day, 'url' => fn ($d) => route('account.bookings.reschedule', [$booking['id'], 'date' => $d])])</div>
        <form method="POST" action="{{ route('account.bookings.reschedule.store', $booking['id']) }}" data-once data-slot-form style="margin-top:20px">
            @csrf
            <x-idem />
            @include('partials.slot-grid', ['slots' => $slots, 'resource' => null])
            <p style="margin-top:22px"><button class="btn btn--lg" type="submit" data-busy="Moving your booking...">Move my booking <span class="arr" aria-hidden="true">&rarr;</span></button></p>
        </form>
    @endif
</div></div>
@endsection
