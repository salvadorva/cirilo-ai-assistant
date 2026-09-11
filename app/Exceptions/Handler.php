<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Log;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        // Manejar específicamente los errores de token CSRF
        $this->renderable(function (TokenMismatchException $e, $request) {
            // Registrar el error en los logs
            Log::warning('Error CSRF detectado', [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'user_agent' => $request->userAgent(),
                'ip' => $request->ip(),
            ]);

            // Si es una solicitud AJAX, devolver respuesta JSON
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'La sesión ha expirado. Por favor, recarga la página e intenta nuevamente.',
                    'code' => 419,
                ], 419);
            }

            // Regenerar el token CSRF
            $request->session()->regenerateToken();

            // Para solicitudes normales, redirigir con mensaje de error
            return redirect()->back()
                ->withInput($request->except('password', 'password_confirmation'))
                ->with('error', 'La sesión ha expirado. Por favor, intenta nuevamente.');
        });
    }
}
