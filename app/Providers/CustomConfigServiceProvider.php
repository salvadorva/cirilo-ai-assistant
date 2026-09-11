<?php

namespace App\Providers;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;

class CustomConfigServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Cargar configuraciones personalizadas
        $this->mergeCustomConfigs();
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Merge custom configuration files.
     */
    protected function mergeCustomConfigs(): void
    {
        // Cargar configuración personalizada de sesión
        if (file_exists(config_path('session.custom.php'))) {
            $customConfig = require config_path('session.custom.php');
            foreach ($customConfig as $key => $value) {
                Config::set('session.'.$key, $value);
            }
        }
    }
}
