@use('App\Support\Lagos')
@use('App\Support\Money')
@php
    $cls = ['CONFIRMED' => 'status--ok', 'HELD' => 'status--hold', 'PENDING_PAYMENT' => 'status--hold', 'COMPLETED' => 'status--done', 'RESCHEDULED' => 'status--done'][$b['status']] ?? '';
@endphp
<li>
    <a href="{{ in_array($b['status'], ['HELD', 'PENDING_PAYMENT'], true) ? route('checkout.show', $b['id']) : route('account.bookings.show', $b['id']) }}" class="row-item">
        <div>
            <p style="font-weight:600">{{ $b['resourceName'] ?? 'Booking' }}</p>
            <p style="font-size:14px;color:var(--mute)">{{ Lagos::parse($b['start'])->format('D j M Y, H:i') }} &middot; {{ $b['number'] }}</p>
        </div>
        <div style="text-align:right">
            <span class="status {{ $cls }}">{{ ucfirst(strtolower(str_replace('_', ' ', $b['status']))) }}</span>
            <p style="font-size:14px;margin-top:4px">{{ Money::format($b['total']) }}</p>
        </div>
    </a>
</li>
