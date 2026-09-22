<?php

/*
|--------------------------------------------------------------------------
| Otueke API client configuration
|--------------------------------------------------------------------------
|
| This application is a UI / backend-for-frontend over the Otueke API. The
| API is the single owner of business data and business rules. Nothing in
| this file may contain secrets: every value is read from the environment.
|
*/

return [

    // Logical name of this application, sent to the API for tracing.
    'service' => 'otueke-booking-web',

    // Timezone used ONLY when rendering dates. The API stores and returns UTC
    // timestamps and the application itself runs in UTC.
    'display_timezone' => env('OTUEKE_DISPLAY_TIMEZONE', 'Africa/Lagos'),

    'api' => [
        // Base URL of the Otueke API (on-site server or cloud instance),
        // e.g. http://otueke-api.site.local:5080 or https://api.example.com
        'base_url' => env('OTUEKE_API_BASE_URL', 'http://127.0.0.1:5080'),

        // Versioned path prefix; all calls go to {base_url}/api/v1/...
        'prefix' => env('OTUEKE_API_PREFIX', '/api/v1'),

        // Request timeout and connect timeout, in seconds.
        'timeout' => (int) env('OTUEKE_API_TIMEOUT', 10),
        'connect_timeout' => (int) env('OTUEKE_API_CONNECT_TIMEOUT', 3),

        // Public identifier of this client application as registered in the
        // API. This is NOT a secret. Client secrets (if ever required) must
        // come from the environment / secret store and never be committed.
        'client_id' => env('OTUEKE_API_CLIENT_ID', 'otueke-booking-web'),

        // Session key under which the API-issued access token is stored
        // server-side. Tokens are never exposed to the browser.
        'session_token_key' => 'otueke.api_token',
    ],

];
