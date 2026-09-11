<?php

namespace App\Http\Middleware;

use App\Models\ApiUsageLog;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckDailyImageLimit
{
    /**
     * Límite diario de generación de imágenes (por defecto)
     */
    const DEFAULT_DAILY_LIMIT = 4;

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Solo aplicar límite a usuarios autenticados
        if (! Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();

        // Los administradores no tienen límite
        if ($user->role && $user->role->name === 'admin') {
            return $next($request);
        }

        // Obtener el límite personalizado del usuario o usar el valor por defecto
        $dailyLimit = $user->daily_image_limit ?? self::DEFAULT_DAILY_LIMIT;

        // Si el límite es 0, no hay restricción (sin límite)
        if ($dailyLimit == 0) {
            return $next($request);
        }

        // Contar imágenes generadas hoy
        $today = Carbon::today();
        $imagesGeneratedToday = ApiUsageLog::where('user_id', $user->id)
            ->where('api_type', 'image_generation')
            ->where('status', 'success')
            ->whereDate('created_at', $today)
            ->count();

        // Verificar si alcanzó el límite
        if ($imagesGeneratedToday >= $dailyLimit) {
            // Si es una petición AJAX, devolver JSON
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'error' => 'Has alcanzado el límite diario de generación de imágenes.',
                    'limit' => $dailyLimit,
                    'used' => $imagesGeneratedToday,
                    'reset_at' => Carbon::tomorrow()->format('H:i'),
                    'message' => "Has generado {$imagesGeneratedToday} de {$dailyLimit} imágenes permitidas hoy. El límite se reiniciará mañana.",
                ], 429); // 429 Too Many Requests
            }

            // Si es una petición normal, redirigir con mensaje
            return redirect()->back()->with('error',
                "Has alcanzado el límite diario de {$dailyLimit} imágenes. ".
                "Has generado {$imagesGeneratedToday} imágenes hoy. ".
                'El límite se reiniciará mañana a las 00:00.'
            );
        }

        // Agregar información del límite a la request para mostrar en la vista
        $request->merge([
            'images_remaining' => $dailyLimit - $imagesGeneratedToday,
            'images_used' => $imagesGeneratedToday,
            'daily_limit' => $dailyLimit,
        ]);

        return $next($request);
    }
}
