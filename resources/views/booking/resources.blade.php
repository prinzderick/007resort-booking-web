@extends('layouts.app')
@section('title', 'Book '.$facility['name'])
@section('description', 'Choose a '.($facility['noun'] ?? 'service').' at '.$facility['name'].' and reserve a time online.')

@section('content')
    <nav aria-label="Breadcrumb" class="text-sm text-stone-500"><a class="hover:underline" href="{{ route('facility', $facility['slug']) }}">{{ $facility['name'] }}</a> / Book</nav>
    <h1 class="mt-2 text-3xl font-semibold">Book: {{ $facility['name'] }}</h1>
    <p class="mt-1 text-stone-600">Choose a {{ $facility['noun'] ?? 'service' }}, then pick a date and time.</p>

    @if ($notice)
        <x-notice type="warn" class="mt-6">{{ $notice }} <a class="underline" href="{{ route('contact') }}">Contact us</a></x-notice>
    @endif

    <ul class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($resources as $r)
            @php $paused = ($r['onlineAvailable'] ?? true) === false; @endphp
            <li class="rounded-xl border border-stone-200 bg-white p-5 shadow-sm">
                <h2 class="font-semibold">{{ $r['name'] }}</h2>
                <p class="mt-1 text-sm text-stone-600">{{ \App\Support\Money::format($r['price']) }} &middot; {{ $r['slotMinutes'] }} min</p>
                @if ($paused)
                    <p class="mt-4 text-sm text-amber-800">{{ $r['onlineNotice'] ?? 'Online booking paused for this item.' }}</p>
                @else
                    <a href="{{ route('book.slots', [$facility['slug'], $r['id']]) }}" class="mt-4 inline-block rounded-lg bg-emerald-800 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-900">See times</a>
                @endif
            </li>
        @endforeach
    </ul>
    @if (! $notice && count($resources) === 0)
        <p class="mt-6 rounded-lg border border-stone-200 bg-white p-4 text-stone-600">Nothing is bookable online right now. Please contact reception.</p>
    @endif
@endsection
