@use('App\Support\Text')
@php
    $site = $ctx->site();
    $customer = app(\App\Services\Online\CustomerService::class)->user();
    $brand = $site['brand']['name'] ?? config('site.name');
    // @section('x', expr) stores HTML-escaped text; decode once so {{ }} escapes exactly once.
    $sec = fn (string $n) => html_entity_decode(trim($__env->yieldContent($n)), ENT_QUOTES | ENT_HTML5);
    $rawTitle = $sec('title');
    $isHome = request()->routeIs('home');
    $pageTitle = ($rawTitle === '' || $isHome) ? ($site['seo']['defaultTitle'] ?: $brand) : sprintf($site['seo']['titleTemplate'] ?: '%s | '.$brand, Text::plain($rawTitle));
    $pageDesc = $sec('description') ?: ($site['seo']['defaultDescription'] ?: '');
    $ogImage = $sec('og_image') ?: ($site['seo']['ogImage']['url'] ?? null);
    $canonical = $sec('canonical') ?: url()->current();
    $hasHero = $__env->hasSection('hero');
    $noindex = $__env->hasSection('noindex');
    $noCta = $__env->hasSection('no_cta') || request()->routeIs('checkout.*', 'payment.*', 'tickets.*', 'login', 'register', 'verify', 'account*');
    $ann = $site['announcement'];
    $annOn = ! empty($ann['enabled']) && filled($ann['text']);
    $wa = $ctx->whatsappUrl($site['contact']['whatsappMessage'] ?? null);
    $preloadImg = $__env->yieldPushContent('preload');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $pageTitle }}</title>
    @if ($pageDesc !== '')<meta name="description" content="{{ Text::excerpt($pageDesc, 200) }}">@endif
    <link rel="canonical" href="{{ $canonical }}">
    @if ($noindex)<meta name="robots" content="noindex, nofollow">@endif
    <meta property="og:type" content="{{ $sec('og_type') ?: 'website' }}">
    <meta property="og:site_name" content="{{ $brand }}">
    <meta property="og:title" content="{{ Text::plain($rawTitle) ?: $pageTitle }}">
    @if ($pageDesc !== '')<meta property="og:description" content="{{ Text::excerpt($pageDesc, 200) }}">@endif
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:locale" content="en_NG">
    @if ($ogImage)<meta property="og:image" content="{{ str_starts_with($ogImage, 'http') ? $ogImage : url($ogImage) }}">@endif
    <meta name="twitter:card" content="{{ $ogImage ? 'summary_large_image' : 'summary' }}">
    <meta name="twitter:title" content="{{ Text::plain($rawTitle) ?: $pageTitle }}">
    @if ($pageDesc !== '')<meta name="twitter:description" content="{{ Text::excerpt($pageDesc, 200) }}">@endif
    @if ($ogImage)<meta name="twitter:image" content="{{ str_starts_with($ogImage, 'http') ? $ogImage : url($ogImage) }}">@endif
    <meta name="theme-color" content="#14110F">
    <link rel="icon" href="/favicon.ico" sizes="any">
    <script nonce="{{ $cspNonce ?? '' }}">
        // Arms scroll-reveals only when JS runs; if the bundle has not marked itself ready shortly, everything is shown.
        (function (d) { d.classList.add('js'); setTimeout(function () { if (!d.dataset.ready) { d.classList.remove('js'); } }, 4000); })(document.documentElement);
    </script>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @fonts
        @stack('preload')
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    @if (config('site.view_transitions'))
        <style>@view-transition{navigation:auto}::view-transition-old(root){animation:.2s ease both vt-out}::view-transition-new(root){animation:.4s ease both vt-in}@keyframes vt-out{to{opacity:0}}@keyframes vt-in{from{opacity:0}}@media (prefers-reduced-motion:reduce){@view-transition{navigation:none}}</style>
    @endif
    @stack('head')
    <x-jsonld :data="[
        '@context' => 'https://schema.org', '@type' => ['Organization', 'SportsActivityLocation'], 'name' => $brand, 'url' => url('/'),
        'logo' => $site['brand']['logo']['url'] ?? null, 'image' => $ogImage, 'telephone' => $site['contact']['phone'] ?? null, 'email' => $site['contact']['email'] ?? null,
        'address' => ! empty($site['contact']['address']) ? ['@type' => 'PostalAddress', 'streetAddress' => $site['contact']['address'], 'addressCountry' => 'NG'] : null,
        'geo' => ! empty($site['contact']['lat']) ? ['@type' => 'GeoCoordinates', 'latitude' => $site['contact']['lat'], 'longitude' => $site['contact']['lng']] : null,
        'openingHoursSpecification' => collect($site['open']['week'])->reject(fn ($w) => $w['closed'])->map(fn ($w) => ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => $w['name'], 'opens' => $w['open'], 'closes' => $w['close']])->values()->all(),
        'sameAs' => array_values($site['social']),
    ]" />
</head>
<body class="{{ $hasHero ? 'has-hero' : 'no-hero' }}">
<a href="#main" class="skip">Skip to content</a>

<header class="site-header {{ $hasHero ? '' : 'is-solid' }}" data-header>
    @if ($annOn)
        <div class="ann" data-ann="{{ substr(md5($ann['text']), 0, 8) }}" role="region" aria-label="Announcement">
            <span>{{ $ann['text'] }}</span>
            @if (! empty($ann['link']))<a href="{{ $ann['link'] }}">See more</a>@endif
            <button type="button" aria-label="Dismiss announcement" data-ann-close>&times;</button>
        </div>
    @endif
    <div class="hd">
        <a class="brand" href="{{ route('home') }}" aria-label="{{ $brand }}, home">
            @if (! empty($site['brand']['logo']['url']))
                <x-img :m="$site['brand']['logo']" sizes="120px" eager alt="" />
            @else
                <span class="zz" aria-hidden="true">007</span>
            @endif
            <span>{{ $brand === '007 Resort & Spa' ? 'Resort & Spa' : $brand }}</span>
        </a>
        <nav class="primary" aria-label="Main">
            @foreach ($ctx->nav() as $n)
                <a href="{{ $n['href'] }}" @if (request()->is(ltrim($n['href'], '/').'*')) aria-current="page" @endif>{{ $n['label'] }}</a>
            @endforeach
        </nav>
        <div class="hd-actions">
            @if ($site['open']['known'])
                <span class="open-pill {{ $site['open']['open'] ? '' : 'is-closed' }}" title="{{ $site['open']['label'] }}">{{ $site['open']['short'] }}</span>
            @endif
            <a class="acct-link" href="{{ $customer ? route('account') : route('login') }}">{{ $customer ? 'My account' : 'Sign in' }}</a>
            <a class="btn btn--sm" href="{{ url('/sports') }}" data-magnetic>{{ $site['booking']['bookingCtaLabel'] ?: 'Book now' }}</a>
            <button class="burger" type="button" aria-expanded="false" aria-controls="drawer" aria-label="Open menu" data-burger><span></span></button>
        </div>
    </div>
</header>

<div class="drawer" id="drawer" data-drawer role="dialog" aria-modal="true" aria-label="Menu" aria-hidden="true">
    <div class="drawer-panel">
        <div class="grab" aria-hidden="true"></div>
        <button class="drawer-close" type="button" aria-label="Close menu" data-drawer-close>&times;</button>
        <nav aria-label="Mobile">
            @foreach ($ctx->nav() as $n)
                <a href="{{ $n['href'] }}"><span>{{ $n['label'] }}</span><small>{{ $n['hint'] ?? '' }}</small></a>
            @endforeach
            <a href="{{ $customer ? route('account') : route('login') }}"><span>{{ $customer ? 'My account' : 'Sign in' }}</span><small>Bookings and tickets</small></a>
        </nav>
        <div class="drawer-foot">
            <a class="btn btn--block btn--lg" href="{{ url('/sports') }}">{{ $site['booking']['bookingCtaLabel'] ?: 'Book now' }}</a>
            @if ($wa)<a class="btn btn--block wa" href="{{ $wa }}" rel="noopener">WhatsApp us</a>@endif
            @if ($site['open']['known'])<p class="tnum" style="text-align:center;color:var(--mute);font-size:14px">{{ $site['open']['label'] }}</p>@endif
        </div>
    </div>
</div>


<main id="main" tabindex="-1">
    @if (session('status') || session('notice') || session('error') || (isset($errors) && $errors->has('form')))
        <div class="wrap" style="padding-top:{{ $hasHero ? '130px' : '20px' }}">
            @if (session('status'))<x-notice type="success">{{ session('status') }}</x-notice>@endif
            @if (session('notice'))<x-notice type="info">{{ session('notice') }}</x-notice>@endif
            @if (session('error'))<x-notice type="error">{{ session('error') }}</x-notice>@endif
            @if (isset($errors) && $errors->has('form'))<x-notice type="error">{{ $errors->first('form') }}</x-notice>@endif
        </div>
    @endif
    @yield('content')
</main>

<footer class="footer" data-footer>
    <div class="wrap">
        <div class="footer-cta">
            <h2 class="h-1">{{ Text::accent($site['brand']['tagline'] ?: 'Your weekend starts here.') }}</h2>
            <div>
                <x-subscribe source="footer" compact />
                <p class="small" style="margin-top:14px;font-size:14px;color:rgb(244 239 230 / .72)">One short email a week. Confirm once, leave any time.</p>
            </div>
        </div>
        <div class="footer-cols">
            <div>
                <a class="brand" href="{{ route('home') }}"><span class="zz" aria-hidden="true">007</span> {{ $brand }}</a>
                @if (filled($site['footer']['text']))<p class="small">{{ $site['footer']['text'] }}</p>@endif
                @if ($site['social'])
                    <div class="social">
                        @foreach ($site['social'] as $net => $url)
                            <a href="{{ $url }}" rel="noopener me" aria-label="{{ ucfirst($net) }}">{{ strtoupper(substr($net, 0, 2)) }}</a>
                        @endforeach
                    </div>
                @endif
            </div>
            <div>
                <h3>Book</h3>
                <ul>
                    <li><a href="{{ url('/sports') }}">Tennis, football, basketball</a></li>
                    <li><a href="{{ route('pool') }}">Pool day passes</a></li>
                    <li><a href="{{ route('book.resources', 'beauty-spa') }}">Spa treatments</a></li>
                    <li><a href="{{ route('memberships.index') }}">Membership</a></li>
                </ul>
            </div>
            <div>
                <h3>Explore</h3>
                <ul>
                    <li><a href="{{ url('/dining') }}">Dining</a></li>
                    <li><a href="{{ route('events.index') }}">What's on</a></li>
                    <li><a href="{{ route('gallery') }}">Gallery</a></li>
                    <li><a href="{{ route('blog.index') }}">Journal</a></li>
                    <li><a href="{{ route('about') }}">About</a></li>
                    <li><a href="{{ route('faq') }}">Questions</a></li>
                </ul>
            </div>
            <div>
                <h3>Visit</h3>
                <ul>
                    @if (! empty($site['contact']['address']))<li>{{ $site['contact']['address'] }}</li>@endif
                    @if (! empty($site['contact']['phone']))<li><a href="tel:{{ preg_replace('/\s+/', '', $site['contact']['phone']) }}">{{ $site['contact']['phone'] }}</a></li>@endif
                    @if (! empty($site['contact']['email']))<li><a href="mailto:{{ $site['contact']['email'] }}">{{ $site['contact']['email'] }}</a></li>@endif
                    @if ($wa)<li><a href="{{ $wa }}" rel="noopener">WhatsApp</a></li>@endif
                    @if ($site['open']['known'])<li class="tnum" style="opacity:.75">{{ $site['open']['label'] }}</li>@endif
                    <li><a href="{{ route('contact') }}">Contact and directions</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <span>&copy; {{ now()->year }} {{ $site['footer']['copyright'] ?: $brand }}</span>
            <nav aria-label="Legal">
                @foreach ($ctx->footerPages() as $p)
                    <a href="{{ in_array($p['slug'], ['terms', 'privacy', 'cookies']) ? url('/'.$p['slug']) : route('pages.show', $p['slug']) }}">{{ $p['title'] }}</a>
                @endforeach
                <span>Payments by Paystack</span>
            </nav>
        </div>
    </div>
</footer>

@unless ($noCta)
    <div class="mobile-cta" data-mobile-cta>
        <a class="btn" href="{{ $isHome ? '#book' : url('/sports') }}">{{ $site['booking']['bookingCtaLabel'] ?: 'Book now' }}</a>
        <a class="btn btn--ghost" href="{{ route('pool') }}">Pool</a>
    </div>
    @if (config('cms.subscribe_popup'))
        <div class="sub-pop" data-subpop role="dialog" aria-labelledby="pop-h" aria-hidden="true">
            <button class="x" type="button" aria-label="Close" data-subpop-close>&times;</button>
            <h3 id="pop-h">Never miss match night.</h3>
            <p>Events and offers, one email a week. No spam, one click to unsubscribe.</p>
            <x-subscribe source="popup" compact />
        </div>
    @endif
@endunless
@stack('modals')
</body>
</html>
