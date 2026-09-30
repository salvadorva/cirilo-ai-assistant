<?php

namespace App\Http\Middleware;

use App\Services\ImageQuotaService;
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
        // La reserva autoritativa vive en ImageQuotaService, también para chat.
        if (! Auth::check()) {
            throw new \Illuminate\Auth\AuthenticationException;
        }

        $user = Auth::user();

        $quota = app(ImageQuotaService::class);
        $dailyLimit = $quota->limit($user);
        if ($dailyLimit === null) {
            return $next($request);
        }

        // Contar imágenes generadas hoy
        $imagesGeneratedToday = $quota->used($user);

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
