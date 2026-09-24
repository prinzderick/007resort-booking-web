{{-- Props: $days (list of local dates), $day (selected), $url (fn(Y-m-d) => string) --}}
<nav aria-label="Choose a date">
    <div class="datestrip" data-datestrip>
        @foreach ($days as $d)
            <a class="day" href="{{ $url($d->format('Y-m-d')) }}" @if ($d->isSameDay($day)) aria-current="date" @endif>
                <small>{{ $loop->first ? 'Today' : $d->format('D') }}</small>
                <b>{{ $d->format('j') }}</b>
                <span>{{ $d->format('M') }}</span>
            </a>
        @endforeach
    </div>
</nav>
@php $ext = ! empty($externalForm); @endphp
@unless ($ext)<form method="GET" class="date-jump">@else<div class="date-jump">@endunless
    <label for="date-jump">Other date</label>
    <input id="date-jump" type="date" name="date" @if ($ext) form="date-jump-form" @endif value="{{ $day->format('Y-m-d') }}" min="{{ \App\Support\Lagos::today()->format('Y-m-d') }}" max="{{ \App\Support\Lagos::today()->addDays((int) config('r007.booking.horizon_days'))->format('Y-m-d') }}">
    @if ($ext)@foreach (request()->except(['date', 'slot', '_token']) as $k => $v)@if (is_scalar($v))<input type="hidden" name="{{ $k }}" value="{{ $v }}" form="date-jump-form">@endif @endforeach @endif
    <button class="btn btn--sm btn--line" type="submit" @if ($ext) form="date-jump-form" @endif>Go</button>
@unless ($ext)</form>@else</div>@endunless
