@extends('layouts.app')
@section('title', 'Unsubscribe')
@section('noindex', '1')
@section('no_cta', '1')
@section('content')
<div class="page-pad"><div class="wrap"><div class="pay-result">
    @switch($state)
        @case('done')
            <h1>You have been unsubscribed.</h1>
            <p class="lede" style="margin:0 auto">We will not email you again. If that was a mistake you can subscribe again from the footer of any page.</p>
            <p style="margin-top:26px"><a class="btn btn--lg" href="{{ route('home') }}">Back to the site</a></p>
            @break
        @case('ready')
            <h1>Leave the list?</h1>
            <p class="lede" style="margin:0 auto 24px">Unsubscribe this address from our weekly email.</p>
            <form method="POST" action="{{ route('newsletter.unsubscribe.store') }}" data-once>@csrf<input type="hidden" name="token" value="{{ $token }}"><button class="btn btn--lg btn--dark" type="submit">Unsubscribe</button></form>
            @break
        @case('unavailable')
            <h1>We could not do that just now.</h1><p class="lede" style="margin:0 auto">Please try the link again in a few minutes.</p>
            @break
        @default
            <h1>That link does not look right.</h1><p class="lede" style="margin:0 auto">Use the unsubscribe link at the bottom of any of our emails, or <a href="{{ route('contact') }}" style="font-weight:600">contact us</a>.</p>
    @endswitch
</div></div></div>
@endsection
