@extends('layouts.app')
@section('title', 'Confirm your subscription')
@section('noindex', '1')
@section('no_cta', '1')
@section('content')
<div class="page-pad"><div class="wrap"><div class="pay-result">
    @switch($state)
        @case('done')
            <div class="done-hero"><div class="check"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></div></div>
            <h1>You are on the list.</h1>
            <p class="lede" style="margin:0 auto">Thanks for confirming. Look out for the weekly email. You can unsubscribe from any message with one click.</p>
            <p style="margin-top:26px"><a class="btn btn--lg" href="{{ route('events.index') }}">See what is on</a></p>
            @break
        @case('ready')
            <h1>One tap to confirm.</h1>
            <p class="lede" style="margin:0 auto 24px">Confirm that this email address should receive our weekly update.</p>
            <form method="POST" action="{{ route('newsletter.confirm.store') }}" data-once>@csrf<input type="hidden" name="token" value="{{ $token }}"><button class="btn btn--lg" type="submit">Confirm subscription</button></form>
            @break
        @case('expired')
            <h1>That link has expired.</h1><p class="lede" style="margin:0 auto">Subscribe again from any page and we will send a fresh confirmation email.</p><p style="margin-top:24px"><a class="btn" href="{{ route('home') }}">Back to the site</a></p>
            @break
        @case('unavailable')
            <h1>We could not check that just now.</h1><p class="lede" style="margin:0 auto">Please try the link again in a few minutes.</p>
            @break
        @default
            <h1>That link does not look right.</h1><p class="lede" style="margin:0 auto">It may be old or incomplete. Subscribe again from any page to get a new confirmation email.</p><p style="margin-top:24px"><a class="btn" href="{{ route('home') }}">Back to the site</a></p>
    @endswitch
</div></div></div>
@endsection
