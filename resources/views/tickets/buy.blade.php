@use('App\Support\Money')
@use('App\Support\Text')
@extends('layouts.app')
@section('title', Text::plain($cms['title'] ?? 'Pool day passes'))
@section('description', $cms['seo']['description'] ?? 'Buy swimming pool day tickets online for adults and children. Each person gets an individual QR ticket.')
@section('hero', '1')

@php $players = max(0, min((int) config('r007.booking.max_tickets_per_order'), (int) request('players', 0))); $pick = request('date'); $firstAdult = true; @endphp

@section('content')
    @include('partials.page-hero', ['image' => $cms['hero'] ?? $cms['heroFallback'], 'eyebrow' => 'Pool and day passes', 'title' => $cms['title'], 'sub' => $cms['subtitle'], 'compact' => true, 'crumbs' => [['Home', route('home')], ['Pool day passes']],
        'ctas' => [['Choose your date', '#tickets']]])

    <div class="slide-over" style="margin-top:0;border-radius:0">
        <section class="section" id="tickets" style="padding-top:clamp(40px,6vw,80px)">
            <div class="wrap book-layout" style="padding-block:0">
                <div>
                    @if ($notice)
                        <x-notice type="warn">{{ $notice }} <a href="{{ route('contact') }}">Contact us</a></x-notice>
                    @else
                        <form method="POST" action="{{ route('pool.order') }}" class="panel" data-once data-ticket-estimate>
                            @csrf
                            <x-idem />
                            <div class="field">
                                <label for="date">Visit date</label>
                                <select id="date" name="date">
                                    @foreach ($dates as $d)<option value="{{ $d->format('Y-m-d') }}" @selected((old('date') ?? $pick) === $d->format('Y-m-d'))>{{ $d->format('l j F Y') }}</option>@endforeach
                                </select>
                                @error('date')<p class="err">{{ $message }}</p>@enderror
                            </div>

                            <fieldset style="border:0;padding:0;margin-top:26px">
                                <legend class="step-label" style="margin:0 0 4px">Tickets</legend>
                                @foreach ($products as $p)
                                    @php $isAdult = $loop->first; @endphp
                                    <div class="ticket-line">
                                        <div><label for="qty-{{ $p['id'] }}"><b>{{ $p['name'] }}</b></label><span class="p">{{ Money::format($p['price']) }} each</span></div>
                                        <span class="qty" data-qty>
                                            <button type="button" aria-label="Fewer {{ $p['name'] }}" data-qty-dec>&minus;</button>
                                            <input id="qty-{{ $p['id'] }}" name="qty[{{ $p['id'] }}]" type="number" inputmode="numeric" min="0" max="{{ $max }}" value="{{ old('qty.'.$p['id'], $isAdult ? $players : 0) }}" data-unit-minor="{{ Money::minor($p['price']) }}">
                                            <button type="button" aria-label="More {{ $p['name'] }}" data-qty-inc>+</button>
                                        </span>
                                    </div>
                                @endforeach
                                @error('qty')<p class="err">{{ $message }}</p>@enderror
                                @if (count($products) === 0)<p class="empty">No ticket types are on sale online right now.</p>@endif
                            </fieldset>

                            <p class="total">
                                <span>Estimated total <small style="color:var(--mute)">(final price is confirmed at checkout)</small></span>
                                <b data-estimate>{{ "\u{20A6}" }}0</b>
                            </p>
                            <button type="submit" data-busy="One moment..." class="btn btn--lg btn--block" style="margin-top:18px">Continue <span class="arr" aria-hidden="true">&rarr;</span></button>
                        </form>
                    @endif
                </div>
                <aside class="book-side">
                    <div class="summary">
                        <h2>How it works</h2>
                        <ol class="stack" style="padding-left:20px;font-size:15px;color:var(--ink-2)">
                            <li>Pick your date and how many of you are coming.</li>
                            <li>Tell us your name, email and phone, then pay securely with Paystack. No account needed.</li>
                            <li>Everyone gets their own QR ticket to show at the gate.</li>
                        </ol>
                    </div>
                    @if (trim($cms['bodyHtml'] ?? '') !== '')<div class="prose" style="font-size:15px">{!! $cms['bodyHtml'] !!}</div>@endif
                </aside>
            </div>
        </section>
        @include('partials.photo-strip', ['items' => $strip, 'title' => 'The pool'])
        <div style="height:60px"></div>
    </div>
@endsection
