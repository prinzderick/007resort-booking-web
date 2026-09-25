<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Cloudflare Turnstile hook for the find-booking form. Off unless TURNSTILE_SITE_KEY is set; with a site
 * key but no secret the widget renders and verification is skipped (useful while wiring up).
 */
final class Turnstile
{
    public static function siteKey(): ?string
    {
        $k = config('r007.checkout.turnstile_site_key');

        return is_string($k) && $k !== '' ? $k : null;
    }

    public static function enabled(): bool
    {
        return self::siteKey() !== null;
    }

    public static function passes(Request $request): bool
    {
        $secret = config('r007.checkout.turnstile_secret');
        if (! self::enabled() || ! is_string($secret) || $secret === '') {
            return true;
        }
        $token = (string) $request->input('cf-turnstile-response');
        if ($token === '') {
            return false;
        }
        try {
            $res = Http::asForm()->timeout(5)->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => $secret, 'response' => $token, 'remoteip' => $request->ip(),
            ]);

            return $res->successful() && ($res->json('success') === true);
        } catch (\Throwable) {
            return false; // fail closed: a broken challenge must not become a bypass
        }
    }
}
