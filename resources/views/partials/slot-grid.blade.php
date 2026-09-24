@use('App\Support\Lagos')
@use('App\Support\Money')
{{-- Slot grid (radios, no <form>): Props: $slots, $resource (nullable) --}}
@php $anyAvailable = collect($slots)->contains(fn ($s) => $s['available'] ?? false); @endphp
@if (empty($slots))
    <p class="panel panel--line">No slots are offered on this day.</p>
@else
    <div class="legend" aria-hidden="true"><span><i></i>Available</span><span><i class="sel"></i>Selected</span><span><i class="bk"></i>Booked</span></div>
    @error('slot')<p class="err" style="margin-bottom:12px;color:var(--bad);font-weight:500">{{ $message }}</p>@enderror
    <div class="slotgrid" role="radiogroup" aria-label="Available times">
        @foreach ($slots as $s)
            @php $ok = ($s['available'] ?? false); $start = Lagos::parse($s['start']); $end = Lagos::parse($s['end']); $price = $s['price'] ?? ($resource['price'] ?? null); $id = 'slot-'.$loop->index; @endphp
            <div class="slot">
                <input type="radio" name="slot" id="{{ $id }}" value="{{ $s['start'] }}|{{ $s['end'] }}" @disabled(! $ok) data-time="{{ $start->format('H:i') }} - {{ $end->format('H:i') }}" data-price="{{ Money::minor($price) }}" data-price-label="{{ Money::format($price) }}">
                @if ($ok)
                    <label for="{{ $id }}"><b>{{ $start->format('H:i') }}</b><span>{{ Money::format($price) }}</span></label>
                @else
                    <label for="{{ $id }}" class="taken" aria-label="{{ $start->format('H:i') }} unavailable"><b>{{ $start->format('H:i') }}</b><span>Taken</span></label>
                @endif
            </div>
        @endforeach
    </div>
    @unless ($anyAvailable)
        <p style="margin-top:18px;color:var(--mute)">Everything is taken on this day. Try another date.</p>
    @endunless
@endif
