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

            return redirect('login');
        }

        $user = Auth::user();
        $roles = explode('|', $role);

        // Depuración
        \Log::info('Rol del usuario: '.$user->role->name);
        \Log::info('Roles permitidos: '.implode(', ', $roles));

        if (! in_array($user->role->name, $roles)) {
            \Log::info('Rol no permitido, redirigiendo a home.');

            return redirect('/'); // o donde quieras redirigir
        }

        return $next($request);
    }
}
