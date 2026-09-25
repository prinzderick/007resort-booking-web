@use('App\Support\Lagos')
@use('App\Support\Money')
@extends('layouts.app')
@section('title', 'Checkout')
@section('noindex', '1')

@section('content')
    @php
        $s = Lagos::parse($start); $e = Lagos::parse($end);
        $holdMin = max(1, (int) round(config('r007.booking.fallback_hold_seconds') / 60));
    @endphp
    @include('checkout._form', [
        'action' => route('checkout.booking.pay'),
        'heading' => 'Almost <i>there.</i>',
        'summaryTitle' => 'Your booking',
        'lines' => array_values(array_filter([
            ['What', $resource['name']],
            ['When', $s->format('D j M Y').', '.$s->format('H:i').' - '.$e->format('H:i')],
            $qty > 1 ? ['People', (string) $qty] : null,
        ])),
        'total' => Money::format($total),
        'payLabel' => 'Pay '.Money::format($total),
        'secondsLeft' => null,
        'account' => null,
        'prefill' => $prefill,
        'release' => null,
        'footnote' => "We hold your slot for {$holdMin} minutes while you pay. If someone else has just taken it, we will tell you straight away and nothing is charged.",
        'recoverUrl' => route('book.slots', [$slug, $resourceId]),
    ])
    <p class="wrap no-print" style="margin-top:-40px;padding-bottom:40px"><a href="{{ route('book.slots', [$slug, $resourceId, 'date' => $s->format('Y-m-d')]) }}" style="font-weight:600">&larr; Change time</a></p>
@endsection
