<?php

/*
| CMS content source. Every word and image on the public site comes from the 007resort-api CMS module
| (managed in the admin). `fixtures` swaps in bundled sample content so the site can be developed and tested offline.
*/

$fixtures = filter_var(env('CMS_FIXTURES', env('R007_MOCK', false)), FILTER_VALIDATE_BOOL);

return [
    // 'fixtures' | 'http'
    'driver' => env('CMS_DRIVER', $fixtures ? 'fixtures' : 'http'),

    // Path prefix of the public CMS endpoints, relative to r007.api.base_url + prefix.
    'path' => env('CMS_API_PATH', 'public/cms'),

    // Server-side cache of CMS responses: fresh for `ttl` seconds; kept `stale_ttl` seconds so the site keeps
    // rendering (stale-if-error) when the API is slow or down.
    'ttl' => (int) env('CMS_CACHE_TTL', 60),
    'stale_ttl' => (int) env('CMS_STALE_TTL', 86400),
    'timeout' => (int) env('CMS_TIMEOUT', 4),

    // Browser/CDN cache headers on public pages.
    'page_max_age' => (int) env('CMS_PAGE_MAX_AGE', 60),

    // Extra origins allowed in img-src (CSP) besides this site and the API host, e.g. a CDN serving CMS media
    // (comma separated: CMS_MEDIA_HOSTS="https://cdn.example.com"). The API origin itself is always allowed.
    'media_hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env('CMS_MEDIA_HOSTS', ''))))),

    // Origins the contact page may embed as the map (CSP frame-src).
    'frame_hosts' => ['https://www.openstreetmap.org', 'https://www.google.com', 'https://maps.google.com'],

    // Optional dismissible newsletter slide-in (after engagement); respects a dismissal cookie/localStorage.
    'subscribe_popup' => filter_var(env('CMS_SUBSCRIBE_POPUP', true), FILTER_VALIDATE_BOOL),

    // Used ONLY when the CMS was never reachable (no cache at all): keeps the "we are back soon" page useful.
    'emergency' => [
        'phone' => env('SITE_PHONE'),
        'email' => env('SITE_EMAIL'),
    ],
];
