@use('App\Support\Money')
@extends('layouts.app')
@section('title', 'Checkout')
@section('noindex', '1')

@section('content')
    @include('checkout._form', [
        'action' => route('checkout.pool.pay'),
        'heading' => 'Almost <i>there.</i>',
        'summaryTitle' => 'Your day passes',
        'lines' => array_merge(
            [['Visit date', $visit->format('l j F Y')]],
            array_map(fn ($r) => [$r['qty'].' x '.$r['name'], $r['sum']], $rows),
        ),
        'total' => Money::format($total),
        'payLabel' => 'Pay '.Money::format($total),
        'secondsLeft' => null,
        'account' => null,
        'prefill' => $prefill,
        'release' => null,
        'footnote' => 'Everyone in your group gets their own QR ticket. Final price is confirmed by our booking system.',
        'recoverUrl' => route('pool'),
    ])
    <p class="wrap no-print" style="margin-top:-40px;padding-bottom:40px"><a href="{{ route('pool', ['date' => $date]) }}" style="font-weight:600">&larr; Change date or tickets</a></p>
@endsection
