<?php

namespace App\Providers;

use App\Services\OtuekeApi\OtuekeApiClient;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(OtuekeApiClient::class, fn (Application $app) => new OtuekeApiClient(
            config: (array) $app['config']->get('otueke.api', []),
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
