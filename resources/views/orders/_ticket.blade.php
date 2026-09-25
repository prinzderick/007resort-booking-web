@use('App\Support\Lagos')
@use('App\Support\QrCode')
{{-- One QR ticket (approved mockup: dark green header, big QR, key facts, reference). Props: $t, $order, $i (0-based), $n (count) --}}
@php
    $svg = 'data:image/svg+xml;base64,'.base64_encode(QrCode::svg((string) $t['qrToken']));
    $from = Lagos::parse($t['validFrom'] ?? null);
    $until = Lagos::parse($t['validUntil'] ?? null);
    $when = null;
    if (($order['kind'] ?? '') === 'booking' && ! empty($order['start'])) {
        $s = Lagos::parse($order['start']); $e = Lagos::parse($order['end']);
        $when = $s->format('D j M Y').', '.$s->format('H:i').' - '.$e->format('H:i');
    } elseif (! empty($order['visitDate'])) {
        $when = Lagos::dayStart(substr((string) $order['visitDate'], 0, 10))?->format('D j M Y');
    } elseif ($from) {
        $when = $from->format('j M Y').($until ? ' to '.$until->format('j M Y') : '');
    }
@endphp
<article class="tkt" style="--i:{{ $i }}" aria-label="Ticket {{ $i + 1 }} of {{ $n }}">
    <div class="tkt-top">
        <span class="eyebrow">007 Resort &amp; Spa{{ $n > 1 ? ' - ticket '.($i + 1).' of '.$n : '' }}</span>
        <h2>{{ $t['name'] ?? ($order['title'] ?? 'Ticket') }}</h2>
    </div>
    <div class="tkt-mid">
        <img src="{{ $svg }}" alt="QR code for {{ $t['name'] ?? 'your ticket' }}. Show it at the entrance." width="260" height="260" data-qr>
        <dl>
            @if ($when)<div><dt>When: </dt><dd class="tnum">{{ $when }}</dd></div>@endif
            @if (! empty($t['holderName']))<div><dt>Name: </dt><dd>{{ $t['holderName'] }}</dd></div>@elseif (! empty($order['guest']['name']))<div><dt>Name: </dt><dd>{{ $order['guest']['name'] }}</dd></div>@endif
            <div><dt>Status: </dt><dd class="status-dot">{{ ucfirst(strtolower((string) ($t['status'] ?? 'ACTIVE'))) }}</dd></div>
            <div class="code">{{ $order['reference'] }}{{ $n > 1 ? ' / '.($i + 1) : '' }}</div>
        </dl>
        <p class="no-print tkt-actions"><a href="#" class="btn btn--line btn--sm" data-save-qr data-name="007-resort-{{ strtolower($order['reference']) }}-{{ $i + 1 }}" hidden>Save image</a></p>
    </div>
</article>
