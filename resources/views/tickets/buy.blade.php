@use('App\Support\Money')
@extends('layouts.app')
@section('title', 'Pool tickets')
@section('description', 'Buy swimming pool day tickets online for adults and children. Each person gets an individual QR ticket.')

@section('content')
    <nav aria-label="Breadcrumb" class="text-sm text-stone-500"><a class="hover:underline" href="{{ route('facility', 'pool') }}">Swimming Pool</a> / Tickets</nav>
    <h1 class="mt-2 text-3xl font-semibold">Pool day tickets</h1>
    <p class="mt-1 text-stone-600">Choose your visit date and how many adults and children. Every person receives their own QR ticket.</p>

    @if ($notice)
        <x-notice type="warn" class="mt-6">{{ $notice }} <a class="underline" href="{{ route('contact') }}">Contact us</a></x-notice>
    @else
        @if (! app(\App\Services\Online\CustomerService::class)->check())
            <x-notice type="info" class="mt-6">You will sign in or create an account before paying, so your tickets are saved in your account. <a class="underline" href="{{ route('login') }}">Sign in</a></x-notice>
        @endif
        <form method="POST" action="{{ route('pool.order') }}" class="mt-6 max-w-xl space-y-5 rounded-xl border border-stone-200 bg-white p-6" data-once data-ticket-estimate>
            @csrf
            <x-idem />
            <div>
                <label for="date" class="block text-sm font-medium">Visit date</label>
                <select id="date" name="date" class="mt-1 block w-full rounded-lg border border-stone-300 px-3 py-2.5">
                    @foreach ($dates as $d)
                        <option value="{{ $d->format('Y-m-d') }}" @selected(old('date') === $d->format('Y-m-d'))>{{ $d->format('l j F Y') }}</option>
                    @endforeach
                </select>
                @error('date')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
            </div>

            <fieldset>
                <legend class="text-sm font-medium">Tickets</legend>
                <ul class="mt-2 divide-y divide-stone-100">
                    @foreach ($products as $p)
                        <li class="flex items-center justify-between gap-4 py-3">
                            <div>
                                <label for="qty-{{ $p['id'] }}" class="font-medium">{{ $p['name'] }}</label>
                                <p class="text-sm text-stone-600">{{ Money::format($p['price']) }} each</p>
                            </div>
                            <input id="qty-{{ $p['id'] }}" name="qty[{{ $p['id'] }}]" type="number" inputmode="numeric" min="0" max="{{ $max }}" value="{{ old('qty.'.$p['id'], 0) }}"
                                   data-unit-minor="{{ Money::minor($p['price']) }}" class="w-20 rounded-lg border border-stone-300 px-3 py-2 text-center text-base">
                        </li>
                    @endforeach
                </ul>
                @error('qty')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                @if (count($products) === 0)<p class="text-sm text-stone-600">No ticket types are on sale online right now.</p>@endif
            </fieldset>

            <p class="flex items-baseline justify-between border-t border-stone-100 pt-4">
                <span class="text-sm text-stone-600">Estimated total <span class="text-xs">(final price is confirmed at checkout)</span></span>
                <strong class="text-xl" data-estimate>{{ "\u{20A6}" }}0</strong>
            </p>
            <button type="submit" data-busy="Redirecting to Paystack..." class="w-full rounded-lg bg-emerald-800 px-4 py-3 font-semibold text-white hover:bg-emerald-900 disabled:opacity-60">Continue to payment</button>
        </form>
    @endif
@endsection
