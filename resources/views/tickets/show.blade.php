@use('App\Support\Lagos')
@extends('layouts.app')
@section('title', count($tickets) > 1 ? 'Your tickets' : 'Your ticket')
@section('noindex', '1')

@section('content')
    <div class="mx-auto max-w-2xl">
        <h1 class="text-2xl font-semibold">{{ count($tickets) > 1 ? 'Your tickets ('.count($tickets).')' : 'Your ticket' }}</h1>
        <p class="mt-1 text-sm text-stone-600">Show the QR code at the entrance. Keep this page, save the image, or print it. Screenshots work too.</p>
        <p class="mt-3 flex flex-wrap gap-2 text-sm print:hidden">
            <button type="button" data-print class="rounded border border-stone-300 bg-white px-3 py-2 hover:bg-stone-100">Print</button>
            <a class="rounded border border-stone-300 bg-white px-3 py-2 hover:bg-stone-100" href="{{ route('account.bookings') }}">My bookings</a>
        </p>

        <div class="mt-6 space-y-6">
            @foreach ($tickets as $t)
                @php $item = $t['items'][0] ?? null; @endphp
                <article class="break-inside-avoid rounded-2xl border border-stone-200 bg-white p-6 text-center shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-widest text-emerald-800">007 Resort &amp; Spa</p>
                    <h2 class="mt-1 text-lg font-semibold">{{ $item['name'] ?? 'Ticket' }}</h2>
                    <img src="{{ route('tickets.qr', $t['id']) }}" alt="QR code for {{ $item['name'] ?? 'ticket' }}" width="260" height="260" class="mx-auto mt-4 h-64 w-64 max-w-full">
                    <dl class="mt-4 grid gap-1 text-sm text-stone-700">
                        @if (! empty($t['holderName']))<div><dt class="inline text-stone-500">Name: </dt><dd class="inline">{{ $t['holderName'] }}</dd></div>@endif
                        @if ($item && $item['validFrom'])<div><dt class="inline text-stone-500">Valid: </dt><dd class="inline">{{ Lagos::parse($item['validFrom'])->format('D j M, H:i') }} &ndash; {{ Lagos::parse($item['validUntil'])?->format('D j M, H:i') }}</dd></div>@endif
                        <div><dt class="inline text-stone-500">Status: </dt><dd class="inline font-medium">{{ ucfirst(strtolower($t['status'])) }}</dd></div>
                        <div class="text-xs text-stone-400">Ticket {{ strtoupper(substr($t['id'], -8)) }}</div>
                    </dl>
                    <p class="mt-4 print:hidden"><a class="text-sm text-emerald-800 underline" href="{{ route('tickets.download', $t['id']) }}">Download QR image (SVG)</a></p>
                </article>
            @endforeach
        </div>
    </div>
@endsection
