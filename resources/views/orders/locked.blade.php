@extends('layouts.app')
@section('title', 'Open your booking')
@section('noindex', '1')
@section('no_cta', '1')
@section('content')
<div class="page-pad"><div class="wrap"><div class="pay-result panel">
    <h1 tabindex="-1" data-focus-me>Open your booking</h1>
    <p class="lede" style="margin:0 auto">For your privacy we open a booking only on the device that made it, or with your reference and the email or phone you booked with. That link may be old or from another device.</p>
    <a class="btn btn--lg" style="margin-top:22px" href="{{ route('find.show', ['reference' => $reference ?? null]) }}">Find my booking</a>
</div></div></div>
@endsection
