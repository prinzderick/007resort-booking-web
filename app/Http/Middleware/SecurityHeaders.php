<?php

namespace App\Http\Middleware;

use App\Support\Turnstile;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline security headers + a request id for log correlation. Logging is
 * privacy-conscious: the id is a random UUID; no IPs, emails, names, tokens.
 *
 * The CSP uses a per-request nonce for the one tiny inline script (arms scroll-reveals only when JS runs) so
 * `script-src` never needs 'unsafe-inline'.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $id = (string) Str::uuid();
        $nonce = base64_encode(random_bytes(16));
        Log::shareContext(['request_id' => $id]);
        View::share('cspNonce', $nonce);

        $response = $next($request);

        $response->headers->set('X-Request-Id', $id);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        if (! $response->headers->has('Referrer-Policy')) {
            $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        }
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if (! config('app.debug')) {
            $turnstile = Turnstile::enabled() ? ' https://challenges.cloudflare.com' : '';
            $img = implode(' ', array_unique(array_filter([...$this->mediaOrigins()])));
            $frames = implode(' ', (array) config('cms.frame_hosts'));
            $response->headers->set('Content-Security-Policy',
                "default-src 'self'; img-src 'self' data: {$img}; style-src 'self' 'unsafe-inline' https://fonts.bunny.net; ".
                "font-src 'self' https://fonts.bunny.net; script-src 'self' 'nonce-{$nonce}'{$turnstile}; connect-src 'self'; frame-src {$frames}{$turnstile}; ".
                "form-action 'self' https://checkout.paystack.com https://*.paystack.com; base-uri 'self'; frame-ancestors 'self'");
            if ($request->isSecure()) {
                $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
            }
        }

        if ($request->hasSession() && $request->session()->has('r007.customer')) {
            $response->headers->set('Cache-Control', 'private, no-store');
        }

        return $response;
    }

    /** @return list<string> */
    private function mediaOrigins(): array
    {
        $out = (array) config('cms.media_hosts');
        $api = parse_url((string) config('r007.api.base_url'));
        if (! empty($api['scheme']) && ! empty($api['host'])) {
            $out[] = $api['scheme'].'://'.$api['host'].(isset($api['port']) ? ':'.$api['port'] : '');
        }

        return $out;
    }
}
