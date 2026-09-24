<?php

namespace App\Providers;

use App\Services\Cms\CachedCmsClient;
use App\Services\Cms\CmsClient;
use App\Services\Cms\FixtureCmsClient;
use App\Services\Cms\HttpCmsClient;
use App\Services\Online\ContentService;
use App\Services\Online\SiteContext;
use App\Services\R007Api\MockR007ApiClient;
use App\Services\R007Api\R007ApiClient;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(R007ApiClient::class, function (Application $app) {
            $class = $app['config']->get('r007.mock') ? MockR007ApiClient::class : R007ApiClient::class;

            return new $class(
                config: (array) $app['config']->get('r007.api', []),
                session: $app->bound('session.store') ? $app['session.store'] : null,
            );
        });

        // CMS content: fixtures offline, or the real API behind a short-TTL / stale-if-error cache.
        $this->app->scoped(CmsClient::class, function (Application $app) {
            $cfg = (array) $app['config']->get('cms');
            if (($cfg['driver'] ?? 'http') === 'fixtures') {
                return new FixtureCmsClient;
            }

            return new CachedCmsClient(
                new HttpCmsClient((array) $app['config']->get('r007.api', []), (string) $cfg['path'], (int) $cfg['timeout']),
                Cache::store(),
                (int) $cfg['ttl'],
                (int) $cfg['stale_ttl'],
            );
        });
        $this->app->scoped(SiteContext::class);
        $this->app->scoped(ContentService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Keyed by IP + session so shared NATs do not starve each other, and by
        // the submitted email for credential endpoints. Nothing personal is logged.
        RateLimiter::for('login', fn (Request $r) => [
            Limit::perMinute(5)->by('login|'.strtolower((string) $r->input('email')).'|'.$r->ip()),
            Limit::perMinute(20)->by('login-ip|'.$r->ip()),
        ]);
        RateLimiter::for('register', fn (Request $r) => [
            Limit::perMinute(3)->by('reg-min|'.$r->ip()),
            Limit::perHour(10)->by('reg-hour|'.$r->ip()),
        ]);
        RateLimiter::for('verify', fn (Request $r) => Limit::perMinute(6)->by('verify|'.strtolower((string) $r->input('email')).'|'.$r->ip()));
        RateLimiter::for('booking', fn (Request $r) => Limit::perMinute(20)->by('booking|'.$r->session()->getId().'|'.$r->ip()));
        RateLimiter::for('payment', fn (Request $r) => Limit::perMinute(10)->by('payment|'.$r->session()->getId().'|'.$r->ip()));
        RateLimiter::for('subscribe', fn (Request $r) => [Limit::perMinute(4)->by('sub|'.$r->ip()), Limit::perHour(20)->by('sub-h|'.$r->ip())]);
        RateLimiter::for('contact', fn (Request $r) => [Limit::perMinute(3)->by('contact|'.$r->ip()), Limit::perHour(12)->by('contact-h|'.$r->ip())]);
        RateLimiter::for('public', fn (Request $r) => Limit::perMinute(120)->by('public|'.$r->ip()));

        View::composer('*', fn ($view) => $view->with('ctx', $this->app->make(SiteContext::class)));
    }
}
