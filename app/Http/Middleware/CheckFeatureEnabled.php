<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckFeatureEnabled
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $feature): mixed
    {
        // Verificar si el feature ya incluye '_enabled' al final
        $featureName = str_ends_with($feature, '_enabled') ? $feature : "{$feature}_enabled";

        if (! config("features.{$featureName}", false)) {
            abort(404);
        }

        return $next($request);
    }
}
