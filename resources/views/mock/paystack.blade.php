@extends('layouts.app')
@section('title', 'Mock Paystack')
@section('noindex', '1')
@section('content')
<div class="page-pad"><div class="wrap"><div class="auth panel" style="border:2px dashed #e0a100">
    <p class="eyebrow" style="color:#7a4b00">Mock API mode - not real Paystack</p>
    <h1 class="h-2">Pay {{ \App\Support\Money::format($payment['amount'] ?? null) }}</h1>
    <p style="color:var(--mute);margin-top:6px">Reference {{ $reference }}</p>
    <form method="POST" class="stack" style="margin-top:22px">
        @csrf
        <button name="outcome" value="success" class="btn btn--lg btn--block">Simulate successful payment</button>
        <button name="outcome" value="fail" class="btn btn--line btn--block">Simulate failed payment</button>
    </form>
</div></div></div>
@endsection
