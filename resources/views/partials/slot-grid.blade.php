@use('App\Support\Lagos')
@use('App\Support\Money')
{{-- Props: $slots, $action, $label, $resource (nullable), $withQuantity (bool) --}}
@php $anyAvailable = collect($slots)->contains(fn ($s) => $s['available'] ?? false); @endphp
@if (empty($slots))
    <p class="rounded-lg border border-stone-200 bg-white p-4 text-stone-600">No slots are offered on this day.</p>
@else
    <form method="POST" action="{{ $action }}" data-once>
        @csrf
        <x-idem />
        @if (! empty($withQuantity))
            <div class="mb-4 max-w-40">
                <label for="quantity" class="block text-sm font-medium">Number of people / units</label>
                <input id="quantity" name="quantity" type="number" min="1" max="{{ $resource['capacity'] ?? 50 }}" value="1" class="mt-1 block w-full rounded-lg border border-stone-300 px-3 py-2">
            </div>
        @endif
        @error('slot')<p class="mb-3 text-sm text-red-700">{{ $message }}</p>@enderror
        <ul class="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6" role="list">
            @foreach ($slots as $s)
                @php $ok = ($s['available'] ?? false); $start = Lagos::parse($s['start']); $end = Lagos::parse($s['end']); @endphp
                <li>
                    <button type="submit" name="slot" value="{{ $s['start'] }}|{{ $s['end'] }}" @disabled(! $ok)
                            @if (! $ok) aria-label="{{ $start->format('H:i') }} unavailable" @endif
                            class="w-full rounded-lg border px-2 py-3 text-center text-sm transition {{ $ok ? 'border-emerald-300 bg-white hover:border-emerald-700 hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-600' : 'cursor-not-allowed border-stone-200 bg-stone-100 text-stone-400 line-through' }}">
                        <span class="block font-semibold">{{ $start->format('H:i') }}<span class="font-normal"> - {{ $end->format('H:i') }}</span></span>
                        <span class="block text-xs {{ $ok ? 'text-stone-600' : '' }}">{{ $ok ? Money::format($s['price'] ?? ($resource['price'] ?? null)) : 'Taken' }}</span>
                    </button>
                </li>
            @endforeach
        </ul>
        @unless ($anyAvailable)
            <p class="mt-4 text-sm text-stone-600">Everything is taken on this day. Try another date.</p>
        @endunless
        <p class="mt-4 text-xs text-stone-500">Choosing a slot holds it for you while you check out. {{ $label ?? '' }}</p>
    </form>
@endif
