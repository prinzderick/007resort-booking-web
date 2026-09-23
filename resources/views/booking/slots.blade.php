@extends('layouts.app')
@section('title', ($resource['name'] ?? 'Choose a time').' - '.$facility['name'])
@section('noindex', '1')

@section('content')
    <nav aria-label="Breadcrumb" class="text-sm text-stone-500">
        <a class="hover:underline" href="{{ route('book.resources', $facility['slug']) }}">{{ $facility['name'] }}</a> / {{ $resource['name'] ?? 'Time' }}
    </nav>
    <h1 class="mt-2 text-3xl font-semibold">{{ $resource['name'] ?? $facility['name'] }}</h1>
    @if ($resource)
        <p class="mt-1 text-stone-600">{{ \App\Support\Money::format($resource['price']) }} &middot; {{ $resource['slotMinutes'] }} min slots &middot; {{ $day->format('l j F Y') }}</p>
    @endif

    @if ($notice)
        <x-notice type="warn" class="mt-6">{{ $notice }} <a class="underline" href="{{ route('contact') }}">Contact us</a></x-notice>
    @else
        <div class="mt-6">
            @include('partials.day-picker', ['days' => $days, 'day' => $day, 'url' => fn ($d) => route('book.slots', [$facility['slug'], $resource['id'], 'date' => $d])])
        </div>

        <div class="mt-6" aria-live="polite">
            @if (! app(\App\Services\Online\CustomerService::class)->check())
                <x-notice type="info">You will be asked to sign in or create an account before a slot is held. <a class="underline" href="{{ route('login') }}">Sign in</a></x-notice>
            @endif
            @include('partials.slot-grid', [
                'slots' => $slots,
                'action' => route('book.hold', [$facility['slug'], $resource['id']]),
                'resource' => $resource,
                'withQuantity' => ($resource['mode'] ?? '') === 'INDIVIDUAL_CAPACITY',
            ])
        </div>
    @endif
@endsection
