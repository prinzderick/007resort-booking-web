@extends('layouts.app')
@section('title', 'My bookings')
@section('noindex', '1')
@section('content')
<div class="page-pad"><div class="wrap">
    <span class="eyebrow">My account</span>
    <h1 class="h-1" style="margin-bottom:26px">My <i>bookings.</i></h1>
    @if ($unavailable)
        <x-notice type="warn">Your booking history is temporarily unavailable. Please try again in a few minutes.</x-notice>
    @endif
    <ul class="stack plain" role="list">
        @forelse ($bookings as $b)
            @include('partials.booking-row', ['b' => $b])
        @empty
            <li class="panel panel--line">No bookings yet.</li>
        @endforelse
    </ul>
</div></div>
@endsection
