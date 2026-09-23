<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cheap spam protection for public forms: an invisible honeypot field and a
 * minimum fill time (bots submit instantly). Pair with <x-spam-fields />.
 */
class SpamGuard
{
    public const MIN_SECONDS = 2;

    public const MAX_SECONDS = 86400;

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->filled('website')) {
            return $this->reject();
        }

        try {
            $elapsed = time() - (int) Crypt::decryptString((string) $request->input('_ts'));
        } catch (\Throwable) {
            return $this->reject();
        }

        if ($elapsed < self::MIN_SECONDS || $elapsed > self::MAX_SECONDS) {
            return $this->reject();
        }

        return $next($request);
    }

    private function reject(): Response
    {
        return back()->withErrors(['form' => 'We could not verify that submission. Please wait a moment and try again.']);
    }
}
