<?php

use App\Http\Middleware\SecurityHeaders;
use App\Services\Cms\CmsUnavailableException;
use App\Services\Online\CustomerService;
use App\Services\R007Api\R007ApiException;
use App\Support\ApiProblem;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);
        // Behind the Cloud VPS reverse proxy (nginx/caddy): trust forwarded proto/host.
        $middleware->trustProxies(at: env('TRUSTED_PROXIES', '127.0.0.1'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Fallback rendering for API errors a controller did not handle itself.
        // Privacy: log status + stable code only, never payloads or identities.
        $exceptions->dontReport([R007ApiException::class, CmsUnavailableException::class]);
        $exceptions->render(function (CmsUnavailableException $e, Request $request) {
            return response()->view('errors.cms', [], 503, ['Retry-After' => '30']);
        });
        $exceptions->render(function (R007ApiException $e, Request $request) {
            Log::warning('api error', ['status' => $e->status, 'code' => $e->code(), 'path' => $request->path()]);

            if ($e->is('unauthenticated', 'token_expired') || $e->status === 401) {
                app(CustomerService::class)->forget();
                if ($request->isMethod('GET')) {
                    $request->session()->put('url.intended', $request->fullUrl());
                }

                return redirect()->route('login')->with('notice', 'Your session has expired. Please sign in again.');
            }

            if ($e->isUnavailable()) {
                return response()->view('errors.unavailable', [], 503);
            }

            if ($e->status === 404) {
                abort(404);
            }

            return response()->view('errors.api', ['message' => ApiProblem::message($e)], $e->status >= 400 && $e->status < 600 ? $e->status : 500);
        });
    })->create();
