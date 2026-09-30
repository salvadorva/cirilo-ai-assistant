<?php

namespace App\Http\Middleware;

use App\Services\Reminders\ReminderApiException;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Contexto común de las APIs de recordatorios: request_id correlacionable y
 * flag independiente por superficie (hermes | mobile).
 */
class ReminderApiContext
{
    public function handle(Request $request, Closure $next, string $surface): Response
    {
        $requestId = (string) Str::uuid();
        $request->attributes->set('reminder_request_id', $requestId);

        if (! config("reminders.{$surface}_api_enabled")) {
            $response = (new ReminderApiException(503, 'feature_disabled', 'La función está temporalmente cerrada.'))->render();
        } else {
            $response = $next($request);
        }
        $response->headers->set('X-Request-ID', $requestId);

        return $response;
    }
}
