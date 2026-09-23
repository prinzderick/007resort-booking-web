<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline security headers + a request id for log correlation. Logging is
 * privacy-conscious: the id is a random UUID; no IPs, emails, names, tokens.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $id = (string) Str::uuid();
        Log::shareContext(['request_id' => $id]);

        $response = $next($request);

        $response->headers->set('X-Request-Id', $id);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if (! config('app.debug')) {
            $response->headers->set('Content-Security-Policy',
                "default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline' https://fonts.bunny.net; ".
                "font-src 'self' https://fonts.bunny.net; script-src 'self'; form-action 'self' https://checkout.paystack.com https://*.paystack.com; frame-ancestors 'self'");
            if ($request->isSecure()) {
                $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
            }
        }

        if ($request->hasSession() && $request->session()->has('r007.customer')) {
            $response->headers->set('Cache-Control', 'private, no-store');
        }

        return $response;
    }
}
