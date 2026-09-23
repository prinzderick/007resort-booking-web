{{-- Props: $days (list of local dates), $day (selected), $url (fn(Y-m-d) => string) --}}
<nav aria-label="Choose a date" class="-mx-4 overflow-x-auto px-4">
    <ul class="flex gap-2 pb-2">
        @foreach ($days as $d)
            <li>
                <a href="{{ $url($d->format('Y-m-d')) }}" @if ($d->isSameDay($day)) aria-current="date" @endif
                   class="block min-w-16 rounded-lg border px-3 py-2 text-center text-sm {{ $d->isSameDay($day) ? 'border-emerald-800 bg-emerald-800 text-white' : 'border-stone-200 bg-white hover:border-emerald-600' }}">
                    <span class="block text-xs uppercase opacity-80">{{ $d->format('D') }}</span>
                    <span class="block text-lg font-semibold leading-tight">{{ $d->format('j') }}</span>
                    <span class="block text-xs opacity-80">{{ $d->format('M') }}</span>
                </a>
            </li>
        @endforeach
    </ul>
</nav>
<form method="GET" class="mt-2 flex items-center gap-2 text-sm">
    <label for="date-jump" class="text-stone-600">Other date</label>
    <input id="date-jump" type="date" name="date" value="{{ $day->format('Y-m-d') }}" min="{{ \App\Support\Lagos::today()->format('Y-m-d') }}" max="{{ \App\Support\Lagos::today()->addDays((int) config('r007.booking.horizon_days'))->format('Y-m-d') }}" class="rounded border border-stone-300 px-2 py-1">
    <button class="rounded border border-stone-300 px-3 py-1 hover:bg-stone-100">Go</button>
</form>
