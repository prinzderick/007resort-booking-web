@extends('layouts.app')
@section('title', 'Back in a moment')
@section('noindex', '1')
@section('hero', '1')
@section('no_cta', '1')
@php $c = $ctx->contact(); $wa = $ctx->whatsappUrl(); @endphp
@section('content')
<section class="lost">
    <div>
        <div class="big" aria-hidden="true">Back<i>.</i></div>
        <h1>The page is warming up.</h1>
        <p>We could not load this page's content just now. Refresh in a few seconds. Bookings and tickets still work from the links below.</p>
        <div class="row">
            <a class="btn btn--lg" href="{{ url()->current() }}">Refresh</a>
            <a class="btn btn--lg btn--ghost" href="{{ route('pool') }}">Pool tickets</a>
            <a class="btn btn--lg btn--ghost" href="{{ route('memberships.index') }}">Membership</a>
            @if ($wa)<a class="btn btn--lg wa" href="{{ $wa }}" rel="noopener">WhatsApp us</a>@endif
        </div>
        @if (! empty($c['phone']))<p style="margin-top:22px">Or call <a href="tel:{{ preg_replace('/\s+/', '', $c['phone']) }}" style="font-weight:600">{{ $c['phone'] }}</a>.</p>@endif
    </div>
</section>
@endsection
