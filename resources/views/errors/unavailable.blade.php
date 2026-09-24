@extends('layouts.app')
@section('title', 'Temporarily unavailable')
@section('noindex', '1')
@section('content')
<div class="page-pad"><div class="wrap"><div class="pay-result hold-card">
    <h1>This part of the site is taking a short break</h1>
    <p style="margin-top:10px">We could not reach our booking system just now. Nothing has been charged. You can browse the rest of the site, try again in a few minutes, or contact reception.</p>
    <p style="margin-top:22px;display:flex;gap:10px;justify-content:center;flex-wrap:wrap"><a class="btn" href="{{ route('home') }}">Back to home</a><a class="btn btn--line" href="{{ route('contact') }}">Contact us</a></p>
</div></div></div>
@endsection
