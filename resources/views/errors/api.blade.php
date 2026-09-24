@extends('layouts.app')
@section('title', 'Something went wrong')
@section('noindex', '1')
@section('content')
<div class="page-pad"><div class="wrap"><div class="pay-result hold-card hold-card--gone">
    <h1>We could not complete that</h1>
    <p style="margin-top:10px">{{ $message }}</p>
    <p style="margin-top:22px"><a class="btn" href="{{ url()->previous() }}">Go back</a></p>
</div></div></div>
@endsection
