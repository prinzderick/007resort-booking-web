@extends('layouts.app')
@section('title', 'Page not found')
@section('noindex', '1')
@section('hero', '1')
@section('no_cta', '1')
@php $img = collect(app(\App\Services\Online\ContentService::class)->fallbackHero())->all() ?: null; @endphp
@section('content')
<section class="lost">
    <div class="ph"><x-img :m="$img" sizes="100vw" alt="" /></div>
    <div>
        <div class="big" aria-hidden="true">4<span class="ball">0</span><i>4</i></div>
        <h1>Out of bounds.</h1>
        <p>That page went over the fence. The link may be old, or the booking may belong to a different account.</p>
        <div class="row"><a class="btn btn--lg" href="{{ route('home') }}">Back to the courts</a><a class="btn btn--lg btn--ghost" href="{{ route('events.index') }}">See what is on</a></div>
    </div>
</section>
@endsection
