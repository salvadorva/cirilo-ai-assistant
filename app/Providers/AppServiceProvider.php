<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Registrar nuestro CustomConfigServiceProvider
        $this->app->register(CustomConfigServiceProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Configurar paginador para usar Bootstrap 5 por defecto
        Paginator::useBootstrapFive();
    }
}
