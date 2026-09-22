<?php

namespace App\Providers;

use App\Services\R007Api\R007ApiClient;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(R007ApiClient::class, fn (Application $app) => new R007ApiClient(
            config: (array) $app['config']->get('r007.api', []),
            session: $app->bound('session.store') ? $app['session.store'] : null,
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
