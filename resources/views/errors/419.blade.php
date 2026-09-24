@extends('layouts.app')
@section('title', 'Page expired')
@section('noindex', '1')
@section('content')
<div class="page-pad"><div class="wrap"><div class="pay-result panel">
    <h1>That page expired</h1>
    <p class="lede" style="margin:0 auto">For your security the form timed out. Nothing was submitted. Please go back, refresh, and try again.</p>
    <a class="btn btn--lg" style="margin-top:22px" href="{{ url()->previous() }}">Go back</a>
</div></div></div>
@endsection
