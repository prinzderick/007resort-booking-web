@use('App\Support\Lagos')
@use('App\Support\Money')
@php
    $badge = ['CONFIRMED' => 'bg-emerald-100 text-emerald-900', 'HELD' => 'bg-amber-100 text-amber-900', 'PENDING_PAYMENT' => 'bg-amber-100 text-amber-900',
        'CANCELLED' => 'bg-stone-200 text-stone-700', 'EXPIRED' => 'bg-stone-200 text-stone-700', 'COMPLETED' => 'bg-sky-100 text-sky-900', 'RESCHEDULED' => 'bg-sky-100 text-sky-900'][$b['status']] ?? 'bg-stone-100';
@endphp
<li>
    <a href="{{ in_array($b['status'], ['HELD', 'PENDING_PAYMENT'], true) ? route('checkout.show', $b['id']) : route('account.bookings.show', $b['id']) }}" class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-stone-200 bg-white p-4 hover:border-emerald-600">
        <div>
            <p class="font-medium">{{ $b['resourceName'] ?? 'Booking' }}</p>
            <p class="text-sm text-stone-600">{{ Lagos::parse($b['start'])->format('D j M Y, H:i') }} &middot; {{ $b['number'] }}</p>
        </div>
        <div class="text-right">
            <span class="inline-block rounded-full px-2.5 py-0.5 text-xs font-medium {{ $badge }}">{{ ucfirst(strtolower(str_replace('_', ' ', $b['status']))) }}</span>
            <p class="mt-1 text-sm">{{ Money::format($b['total']) }}</p>
        </div>
    </a>
</li>
