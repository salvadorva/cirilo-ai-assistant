<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class GameProgressMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $user = Auth::user();

            // Obtener o crear progreso de juego
            $gameProgress = $user->getOrCreateGameProgress();

            // Obtener notificaciones no leídas
            $unreadNotifications = $user->getUnreadNotificationsCount();

            // Obtener total de notificaciones (para Sprint 9)
            $totalNotifications = $user->notifications()->active()->count();

            // Obtener logros destacados
            $showcasedAchievements = $user->getShowcasedAchievements();

            // Compartir datos con todas las vistas
            View::share([
                'gameProgress' => $gameProgress,
                'unreadNotificationsCount' => $unreadNotifications,
                'totalNotifications' => $totalNotifications,
                'showcasedAchievements' => $showcasedAchievements,
            ]);
        }

        return $next($request);
    }
}
