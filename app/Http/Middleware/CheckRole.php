<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, $role)
    {
        if (! Auth::check()) {
            \Log::info('Usuario no autenticado, redirigiendo a login.');

            throw new \Illuminate\Auth\AuthenticationException;
        }

        $user = Auth::user();
        $roles = explode('|', $role);

        abort_unless(in_array($user->role?->name, $roles, true), 403);

        return $next($request);
    }
}
