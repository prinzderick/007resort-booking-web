@php
    $customer = app(\App\Services\Online\CustomerService::class)->user();
    $pageTitle = trim($__env->yieldContent('title')) ?: config('app.name');
    $pageDesc = trim($__env->yieldContent('description')) ?: '007 Resort & Spa: book sports courts, spa and salon appointments, pool tickets and memberships online.';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $pageTitle }}@if ($pageTitle !== config('app.name')) &middot; {{ config('app.name') }}@endif</title>
        <meta name="description" content="{{ $pageDesc }}">
        <link rel="canonical" href="{{ url()->current() }}">
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ config('app.name') }}">
        <meta property="og:title" content="{{ $pageTitle }}">
        <meta property="og:description" content="{{ $pageDesc }}">
        <meta property="og:url" content="{{ url()->current() }}">
        <meta name="theme-color" content="#065f46">
        @hasSection('noindex')
            <meta name="robots" content="noindex, nofollow">
        @endif
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
        @stack('head')
    </head>
    <body class="min-h-screen bg-stone-50 text-stone-900 antialiased flex flex-col">
        <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-2 focus:top-2 focus:z-50 focus:rounded focus:bg-white focus:px-3 focus:py-2 focus:shadow">Skip to content</a>

        <header class="sticky top-0 z-30 border-b border-stone-200 bg-white/95 backdrop-blur">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3">
                <a href="{{ route('home') }}" class="flex items-center gap-2 font-semibold text-emerald-900">
                    <span class="grid h-8 w-8 place-items-center rounded-full bg-emerald-800 text-xs font-bold text-amber-300" aria-hidden="true">007</span>
                    <span class="hidden sm:inline">Resort &amp; Spa</span>
                </a>
                <nav aria-label="Main" class="flex items-center gap-1 text-sm">
                    <a class="rounded px-2 py-2 hover:bg-stone-100" href="{{ route('book.resources', 'sports-arena') }}">Sports</a>
                    <a class="rounded px-2 py-2 hover:bg-stone-100" href="{{ route('book.resources', 'beauty-spa') }}">Spa</a>
                    <a class="hidden rounded px-2 py-2 hover:bg-stone-100 sm:inline" href="{{ route('book.resources', 'salon') }}">Salon</a>
                    <a class="rounded px-2 py-2 hover:bg-stone-100" href="{{ route('pool') }}">Pool</a>
                    <a class="hidden rounded px-2 py-2 hover:bg-stone-100 md:inline" href="{{ route('memberships.index') }}">Membership</a>
                    @if ($customer)
                        <a class="ml-1 rounded bg-emerald-800 px-3 py-2 font-medium text-white hover:bg-emerald-900" href="{{ route('account') }}">My account</a>
                    @else
                        <a class="ml-1 rounded bg-emerald-800 px-3 py-2 font-medium text-white hover:bg-emerald-900" href="{{ route('login') }}">Sign in</a>
                    @endif
                </nav>
            </div>
        </header>

        <main id="main" class="mx-auto w-full max-w-6xl flex-1 px-4 py-8">
            @if (session('status'))
                <x-notice type="success">{{ session('status') }}</x-notice>
            @endif
            @if (session('notice'))
                <x-notice type="info">{{ session('notice') }}</x-notice>
            @endif
            @if (session('error'))
                <x-notice type="error">{{ session('error') }}</x-notice>
            @endif
            @if (isset($errors) && $errors->has('form'))
                <x-notice type="error">{{ $errors->first('form') }}</x-notice>
            @endif
            @yield('content')
        </main>

        <footer class="mt-12 border-t border-stone-200 bg-white">
            <div class="mx-auto grid max-w-6xl gap-6 px-4 py-8 text-sm text-stone-600 sm:grid-cols-3">
                <div>
                    <p class="font-semibold text-stone-900">007 Resort &amp; Spa</p>
                    <p class="mt-1">{{ config('site.contact.address') }}</p>
                </div>
                <div>
                    <p class="font-semibold text-stone-900">Book online</p>
                    <ul class="mt-1 space-y-1">
                        <li><a class="hover:underline" href="{{ route('book.resources', 'sports-arena') }}">Sports courts</a></li>
                        <li><a class="hover:underline" href="{{ route('book.resources', 'beauty-spa') }}">Spa treatments</a></li>
                        <li><a class="hover:underline" href="{{ route('pool') }}">Pool tickets</a></li>
                        <li><a class="hover:underline" href="{{ route('memberships.index') }}">Memberships</a></li>
                    </ul>
                </div>
                <div>
                    <p class="font-semibold text-stone-900">Help</p>
                    <ul class="mt-1 space-y-1">
                        <li><a class="hover:underline" href="{{ route('contact') }}">Contact &amp; location</a></li>
                        <li>Payments are processed securely by Paystack. We never see your card details.</li>
                    </ul>
                </div>
            </div>
        </footer>
    </body>
</html>
