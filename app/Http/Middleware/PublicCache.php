<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser cache headers for public, GET, signed-out CMS pages (short max-age; content changes in the admin show
 * up within the CMS cache TTL). Never applied to signed-in customers, flashes or non-200 responses.
 */
class PublicCache
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $signedIn = $request->hasSession() && $request->session()->has('r007.customer');
        $flashed = $request->hasSession() && ($request->session()->get('_flash.old', []) !== [] || $request->session()->has('status') || $request->session()->has('error') || $request->session()->has('notice'));

        if ($request->isMethod('GET') && $response->getStatusCode() === 200 && ! $signedIn && ! $flashed) {
            $age = (int) config('cms.page_max_age', 60);
            $response->headers->set('Cache-Control', "private, max-age={$age}, stale-while-revalidate=300");
            $response->headers->set('Vary', 'Cookie');
        }

        return $response;
    }
}
