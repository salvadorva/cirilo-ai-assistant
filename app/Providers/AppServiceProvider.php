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
        $this->app->scoped(\App\Services\AiTelemetry::class);
        $this->app->scoped(\App\Services\InteractionTracker::class);
        $this->app->bind(\App\Services\Reminders\Push\PushTransport::class, \App\Services\FcmService::class);
        // Registrar nuestro CustomConfigServiceProvider
        $this->app->register(CustomConfigServiceProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Http\Client\Events\RequestSending::class,
            fn ($event) => app(\App\Services\AiTelemetry::class)->sending($event));
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Http\Client\Events\ResponseReceived::class,
            fn ($event) => app(\App\Services\AiTelemetry::class)->received($event));
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Http\Client\Events\ConnectionFailed::class,
            fn ($event) => app(\App\Services\AiTelemetry::class)->failed($event));
        // Configurar paginador para usar Bootstrap 5 por defecto
        Paginator::useBootstrapFive();
    }
}
