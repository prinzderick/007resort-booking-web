@use('App\Support\Money')
@extends('layouts.app')
@section('title', 'Checkout')
@section('noindex', '1')

@section('content')
    @include('checkout._form', [
        'action' => route('memberships.buy', $plan['id']),
        'heading' => 'Almost <i>a member.</i>',
        'summaryTitle' => 'Your membership',
        'lines' => [
            ['Plan', $plan['name']],
            ['Valid for', $plan['durationDays'].' days from today'],
            ['Visits', ($plan['visitLimit'] ?? null) ? $plan['visitLimit'].' visits' : 'Unlimited visits'],
        ],
        'total' => Money::format($plan['price']),
        'payLabel' => 'Pay '.Money::format($plan['price']),
        'secondsLeft' => null,
        'account' => null,
        'prefill' => $prefill,
        'release' => null,
        'footnote' => 'Your membership QR is shown as soon as payment is confirmed.',
        'recoverUrl' => route('memberships.index'),
    ])
    <p class="wrap no-print" style="margin-top:-40px;padding-bottom:40px"><a href="{{ route('memberships.index') }}" style="font-weight:600">&larr; Back to plans</a></p>
@endsection
