@extends('layouts.app')
@section('title', 'Something went wrong')
@section('noindex', '1')
@section('hero', '1')
@section('no_cta', '1')
@section('content')
<section class="lost">
    <div>
        <div class="big" aria-hidden="true">5<i>0</i>0</div>
        <h1>We dropped the ball.</h1>
        <p>Something broke on our side and we have been told. Nothing has been charged. Give it a moment and try again.</p>
        <div class="row"><a class="btn btn--lg" href="{{ url()->current() }}">Try again</a><a class="btn btn--lg btn--ghost" href="{{ route('home') }}">Back to the home page</a></div>
    </div>
</section>
@endsection
