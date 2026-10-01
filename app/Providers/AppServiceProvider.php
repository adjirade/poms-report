<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Paksa HTTPS untuk semua URL yang di-generate (asset, route, redirect)
        // ketika APP_FORCE_HTTPS=true (produksi di belakang proxy/load balancer
        // TLS). Default false agar pengembangan lokal tetap memakai http://.
        if ((bool) config('hq.force_https', false)) {
            URL::forceScheme('https');
            URL::forceRootUrl((string) config('app.url'));
        }
    }
}
