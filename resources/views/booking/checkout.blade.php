@use('App\Support\Lagos')
@use('App\Support\Money')
@extends('layouts.app')
@section('title', 'Checkout')
@section('noindex', '1')

@section('content')
    @php
        $start = Lagos::parse($booking['start']);
        $end = Lagos::parse($booking['end']);
        $live = $secondsLeft > 0 && in_array($booking['status'], ['HELD', 'PENDING_PAYMENT'], true);
    @endphp
    @include('checkout._form', [
        'action' => route('checkout.pay', $booking['id']),
        'heading' => 'Almost <i>there.</i>',
        'summaryTitle' => 'Your booking',
        'lines' => [
            ['What', $booking['resourceName'] ?? 'Booking'],
            ['When', $start->format('D j M Y').', '.$start->format('H:i').' - '.$end->format('H:i')],
            ['Quantity', (string) ($booking['quantity'] ?? 1)],
            ['Reference', $booking['number'] ?? ''],
        ],
        'total' => Money::format($booking['total']),
        'payLabel' => 'Pay '.Money::format($booking['total']),
        'secondsLeft' => $live ? $secondsLeft : 0,
        'account' => $account,
        'prefill' => $prefill,
        'release' => $live ? route('checkout.release', $booking['id']) : null,
        'recoverUrl' => $slug ? route('book.slots', [$slug, $booking['resourceId']]) : route('home'),
    ])
@endsection
