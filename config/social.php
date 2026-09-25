<?php

/*
| Social sign-up / sign-in (Google, Facebook; Apple designed for later).
|
| This site runs the OAuth authorization-code flow with the provider (Laravel Socialite) and then calls the API
| server-to-server (service token). Provider access tokens are NEVER stored or logged: we read the profile once
| and drop the token. Redirect URIs are ALWAYS built from APP_URL, never from request headers.
*/

$csv = fn (string $v): array => array_values(array_filter(array_map('trim', explode(',', $v))));

return [
    // Providers this site may offer (comma list). A provider also needs credentials (or fake mode) AND to be
    // enabled in the API (`GET public/customers/social/providers`) before its button is shown.
    'enabled' => $csv((string) env('SOCIAL_ENABLED_PROVIDERS', 'google,facebook')),

    // Local fake provider for demos/tests without Google/Facebook credentials. Enforced in code: it can never be
    // active when APP_ENV=production (see App\Services\Social\SocialProviders::fakeEnabled()).
    'fake' => filter_var(env('SOCIAL_FAKE', false), FILTER_VALIDATE_BOOL),

    'providers' => [
        'google' => [
            'label' => 'Google',
            'client_id' => env('SOCIAL_GOOGLE_CLIENT_ID'),
            'client_secret' => env('SOCIAL_GOOGLE_CLIENT_SECRET'),
            'scopes' => ['openid', 'email', 'profile'],
        ],
        'facebook' => [
            'label' => 'Facebook',
            'client_id' => env('SOCIAL_FACEBOOK_CLIENT_ID'),
            'client_secret' => env('SOCIAL_FACEBOOK_CLIENT_SECRET'),
            'scopes' => ['email', 'public_profile'],
        ],
        // 'apple' => designed for later: needs the Sign in with Apple service id + a signed client secret (JWT).
    ],

    // Where a customer's provider avatar may be loaded from (also added to the CSP img-src). https only.
    'avatar_hosts' => array_values(array_unique([
        'https://lh3.googleusercontent.com',
        'https://platform-lookaside.fbsbx.com',
        'https://graph.facebook.com',
        ...$csv((string) env('SOCIAL_AVATAR_HOSTS', '')),
    ])),

    // Wildcard-suffix hosts (Facebook serves avatars from regional fbcdn.net hosts).
    'avatar_host_suffixes' => ['.fbcdn.net', '.googleusercontent.com', '.fbsbx.com'],

    // A pending sign-up (consent / profile / link steps) is forgotten after this many minutes.
    'pending_ttl_minutes' => 20,

    // Seconds the API's provider list is cached (fail closed: no list, no buttons).
    'providers_cache_seconds' => 60,
];
