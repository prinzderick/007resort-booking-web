@extends('layouts.app')
@section('title', 'Mock Paystack')
@section('noindex', '1')
@section('content')
    <div class="mx-auto max-w-md rounded-xl border-2 border-dashed border-amber-400 bg-white p-6">
        <p class="text-xs font-bold uppercase tracking-widest text-amber-700">Mock API mode - not real Paystack</p>
        <h1 class="mt-2 text-xl font-semibold">Pay {{ \App\Support\Money::format($payment['amount'] ?? null) }}</h1>
        <p class="mt-1 text-sm text-stone-600">Reference {{ $reference }}</p>
        <form method="POST" class="mt-5 grid gap-3">
            @csrf
            <button name="outcome" value="success" class="rounded-lg bg-emerald-800 px-4 py-3 font-semibold text-white">Simulate successful payment</button>
            <button name="outcome" value="fail" class="rounded-lg border border-red-300 px-4 py-3 text-red-800">Simulate failed payment</button>
        </form>
    </div>
@endsection
