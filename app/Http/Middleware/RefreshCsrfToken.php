<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class RefreshCsrfToken
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Verificar si la sesión está activa
        if (Session::isStarted()) {
            // Obtener el tiempo de la última regeneración del token
            $lastTokenRefresh = Session::get('last_token_refresh', 0);
            $now = time();

            // Si han pasado más de 30 minutos desde la última regeneración
            if ($now - $lastTokenRefresh > 1800) {
                // Regenerar el token CSRF
                $request->session()->regenerateToken();
                // Guardar el tiempo de la última regeneración
                Session::put('last_token_refresh', $now);
            }
        }

        return $next($request);
    }
}
