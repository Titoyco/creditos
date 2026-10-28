<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;

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
        // Forzar HTTPS en producción o si la app corre en tiendaslamarina
        if (config('app.env') === 'production' || str_contains((string)config('app.url'), 'https://') || (!app()->runningInConsole() && str_contains(request()->getHost(), 'tiendaslamarina'))) {
            URL::forceScheme('https');
        }

        Schema::defaultStringLength(191); // Para evitar problemas con índices en MySQL < 5.7
    }
}
