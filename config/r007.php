<?php

/*
|--------------------------------------------------------------------------
| 007 Resort & Spa API client configuration
|--------------------------------------------------------------------------
|
| This application is a UI / backend-for-frontend over the 007 Resort & Spa
| API. The API is the single owner of business data and business rules.
| Nothing in this file may contain secrets: every value is read from the
| environment.
|
*/

return [

    // Logical name of this application, sent to the API for tracing.
    'service' => '007resort-booking-web',

    // Timezone used ONLY when rendering dates. The API stores and returns UTC
    // timestamps and the application itself runs in UTC.
    'display_timezone' => env('R007_DISPLAY_TIMEZONE', 'Africa/Lagos'),

    'api' => [
        // Base URL of the 007 Resort & Spa API (on-site server or cloud
        // instance), e.g. http://r007-api.site.local:5080 or
        // https://api.example.com
        'base_url' => env('R007_API_BASE_URL', 'http://127.0.0.1:5080'),

        // Versioned path prefix; all calls go to {base_url}/api/v1/...
        'prefix' => env('R007_API_PREFIX', '/api/v1'),

        // Request timeout and connect timeout, in seconds.
        'timeout' => (int) env('R007_API_TIMEOUT', 10),
        'connect_timeout' => (int) env('R007_API_CONNECT_TIMEOUT', 3),

        // Public identifier of this client application as registered in the
        // API. This is NOT a secret. Client secrets (if ever required) must
        // come from the environment / secret store and never be committed.
        'client_id' => env('R007_API_CLIENT_ID', '007resort-booking-web'),

        // Session key under which the API-issued access token is stored
        // server-side. Tokens are never exposed to the browser.
        'session_token_key' => 'r007.api_token',

        // Service credential of the "online" channel (this website). Used for
        // public reads/holds when no customer is signed in. Secret: env only.
        'service_token' => env('R007_API_SERVICE_TOKEN'),
    ],

    // Mock API mode: run the whole site without the backend. State lives in
    // the cache store (never a business database). Development/demo only.
    'mock' => (bool) env('R007_MOCK', false),

    // Booking UX (presentation only: the API owns the real rules).
    'booking' => [
        // How many days ahead the date picker offers.
        'horizon_days' => (int) env('R007_BOOKING_HORIZON_DAYS', 30),
        // Used only if the API omits holdExpiresAt.
        'fallback_hold_seconds' => 600,
        // Pool ticket quantity guard-rails (UI hints; API validates).
        'max_tickets_per_order' => 20,
    ],

];
