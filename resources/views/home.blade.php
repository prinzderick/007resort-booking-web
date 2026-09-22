@extends('layouts.app')

@section('title', config('app.name'))

@section('content')
    <h1 class="text-2xl font-semibold">Welcome</h1>
    <p class="mt-2 text-stone-600">
        Explore our facilities and book online. This is a placeholder page: facility details,
        live availability and booking will be served by the Otueke API booking engine
        (the same engine used by Reception, so slots can never be double-booked).
    </p>

    {{-- Static placeholder content. Real facility data will come from the API. --}}
    <section class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3" aria-label="Facilities">
        @foreach ([
            ['Sports', 'Courts and pitches bookable by time slot.'],
            ['Spa', 'Treatments and appointments.'],
            ['Swimming pool', 'Day tickets with QR entry.'],
            ['Restaurant & bar', 'Dining and events.'],
            ['Events & halls', 'Enquiries and reservations.'],
            ['Memberships', 'Plans and member benefits.'],
        ] as [$name, $blurb])
            <div class="rounded-lg border border-stone-200 bg-white p-4">
                <h2 class="font-medium">{{ $name }}</h2>
                <p class="mt-1 text-sm text-stone-500">{{ $blurb }}</p>
                <p class="mt-3 text-xs text-stone-400">Booking coming soon</p>
            </div>
        @endforeach
    </section>
@endsection
