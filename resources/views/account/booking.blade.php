@use('App\Support\Lagos')
@use('App\Support\Money')
@extends('layouts.app')
@section('title', 'Booking '.$booking['number'])
@section('noindex', '1')
@section('content')
    <nav class="text-sm text-stone-500" aria-label="Breadcrumb"><a class="hover:underline" href="{{ route('account.bookings') }}">My bookings</a> / {{ $booking['number'] }}</nav>
    <h1 class="mt-2 text-3xl font-semibold">{{ $booking['resourceName'] ?? 'Booking' }}</h1>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <section class="rounded-xl border border-stone-200 bg-white p-6 lg:col-span-2">
            <dl class="grid gap-4 text-sm sm:grid-cols-2">
                <div><dt class="text-stone-500">Status</dt><dd class="font-medium">{{ ucfirst(strtolower(str_replace('_', ' ', $booking['status']))) }}</dd></div>
                <div><dt class="text-stone-500">Reference</dt><dd class="font-medium">{{ $booking['number'] }}</dd></div>
                <div><dt class="text-stone-500">When</dt><dd class="font-medium">{{ Lagos::parse($booking['start'])->format('D j M Y, H:i') }} - {{ Lagos::parse($booking['end'])->format('H:i') }}</dd></div>
                <div><dt class="text-stone-500">Total</dt><dd class="font-medium">{{ Money::format($booking['total']) }} (paid {{ Money::format($booking['amountPaid']) }})</dd></div>
            </dl>

            @if ($entitlement)
                <div class="mt-6 border-t border-stone-100 pt-6 text-center">
                    <img src="{{ route('tickets.qr', $entitlement['id']) }}" alt="QR ticket" width="200" height="200" class="mx-auto h-48 w-48">
                    <p class="mt-3 text-sm"><a class="rounded bg-emerald-800 px-4 py-2 text-white" href="{{ route('tickets.show', $entitlement['id']) }}">Open ticket</a></p>
                </div>
            @endif
        </section>

        <aside class="space-y-4">
            @if (in_array($booking['status'], ['HELD', 'PENDING_PAYMENT'], true))
                <a class="block rounded-lg bg-amber-500 px-4 py-3 text-center font-semibold text-amber-950" href="{{ route('checkout.show', $booking['id']) }}">Complete payment</a>
            @endif

            @if ($rules['note'] || $rules['cancelBy'])
                <div class="rounded-xl border border-stone-200 bg-white p-4 text-sm text-stone-700">
                    <p class="font-medium">Change &amp; cancellation</p>
                    @if ($rules['note'])<p class="mt-1">{{ $rules['note'] }}</p>@endif
                    @if ($rules['cancelBy'])<p class="mt-1">Free changes until {{ Lagos::parse($rules['cancelBy'])->format('D j M, H:i') }}.</p>@endif
                    @if ($rules['refundAmount'] !== null)<p class="mt-1">Refund if cancelled now: <strong>{{ Money::format($rules['refundAmount']) }}</strong></p>@endif
                </div>
            @endif

            @if ($rules['canReschedule'])
                <a class="block rounded-lg border border-stone-300 bg-white px-4 py-3 text-center hover:bg-stone-50" href="{{ route('account.bookings.reschedule', $booking['id']) }}">Reschedule</a>
            @endif
            @if ($rules['canCancel'])
                <form method="POST" action="{{ route('account.bookings.cancel', $booking['id']) }}" data-once data-confirm="Cancel this booking?" class="rounded-xl border border-red-200 bg-white p-4">
                    @csrf
                    <x-idem />
                    <label for="reason" class="text-sm font-medium">Reason (optional)</label>
                    <input id="reason" name="reason" maxlength="300" class="mt-1 block w-full rounded border border-stone-300 px-3 py-2 text-sm">
                    <button class="mt-3 w-full rounded-lg border border-red-300 px-4 py-2 text-red-800 hover:bg-red-50">Cancel booking</button>
                </form>
            @elseif ($booking['status'] === 'CONFIRMED')
                <p class="rounded-xl border border-stone-200 bg-stone-100 p-4 text-sm text-stone-600">This booking can no longer be changed online. Please contact reception.</p>
            @endif
        </aside>
    </div>
@endsection
