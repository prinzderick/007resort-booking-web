<?php

namespace App\Http\Middleware;

use App\Services\Online\CustomerService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireCustomer
{
    public function __construct(private readonly CustomerService $customers) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->customers->check()) {
            // GET: come back here. POST (e.g. a slot hold): come back to the page
            // that hosted the form (same-origin referer only), never replay the POST.
            $back = $request->isMethod('GET') ? $request->fullUrl() : $request->headers->get('referer');
            if ($back && parse_url($back, PHP_URL_HOST) === $request->getHost()) {
                $request->session()->put('url.intended', $back);
            }

            return redirect()->route('login')->with('notice', 'Please sign in to continue.');
        }

        return $next($request);
    }
}
