<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

class FeatureMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  string  $feature
     * @return mixed
     */
    public function handle(Request $request, Closure $next, $feature)
    {
        // Verificar si la característica está habilitada
        if (! Config::get("features.{$feature}", false)) {
            abort(404, 'Esta funcionalidad no está disponible actualmente.');
        }

        return $next($request);
    }
}
