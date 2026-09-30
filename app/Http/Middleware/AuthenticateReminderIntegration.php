<?php

namespace App\Http\Middleware;

use App\Models\ReminderIntegration;
use App\Services\Reminders\ReminderApiException;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Credencial opaca exclusiva de integración (no Sanctum). Solo protege las
 * rutas de Hermes; no autentica ningún usuario en web ni en la API móvil.
 */
class AuthenticateReminderIntegration
{
    public function handle(Request $request, Closure $next, string $scope): Response
    {
        $limits = config('reminders.rate_limits');
        $ipKey = 'reminders:auth-fail:'.$request->ip();
        if (RateLimiter::tooManyAttempts($ipKey, $limits['auth_failures_per_minute'])) {
            throw $this->rateLimited(RateLimiter::availableIn($ipKey));
        }

        $token = $request->bearerToken();
        $integration = $token ? ReminderIntegration::findActiveByToken($token) : null;
        if (! $integration) {
            RateLimiter::hit($ipKey, 60);
            throw new ReminderApiException(401, 'unauthenticated', 'Credencial inválida, vencida o revocada.');
        }
        if (! $integration->hasScope($scope)) {
            throw new ReminderApiException(403, 'insufficient_scope', 'La credencial no permite esta operación.');
        }

        $read = $request->isMethod('GET');
        $key = 'reminders:integration:'.$integration->id.($read ? ':read' : ':mutation');
        if (RateLimiter::tooManyAttempts($key, $limits[$read ? 'reads_per_minute' : 'mutations_per_minute'])) {
            throw $this->rateLimited(RateLimiter::availableIn($key));
        }
        RateLimiter::hit($key, 60);

        $integration->forceFill(['last_used_at' => now()])->saveQuietly();
        $request->attributes->set('reminder_integration', $integration);

        return $next($request);
    }

    private function rateLimited(int $seconds): ReminderApiException
    {
        return new ReminderApiException(429, 'rate_limited', 'Demasiadas solicitudes.', [], ['Retry-After' => (string) max(1, $seconds)]);
    }
}
