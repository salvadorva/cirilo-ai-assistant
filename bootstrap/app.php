<?php

use App\Http\Middleware\CheckDailyImageLimit;
use App\Http\Middleware\CheckFeatureEnabled;
use App\Http\Middleware\CheckRole;
use App\Http\Middleware\GameProgressMiddleware;
use App\Http\Middleware\GuardAiRequests;
use App\Http\Middleware\RefreshCsrfToken;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Registrar middleware global para refrescar token CSRF
        $middleware->web([
            RefreshCsrfToken::class,
            GameProgressMiddleware::class,
            GuardAiRequests::class,
        ]);

        $middleware->api(append: [GuardAiRequests::class]);
        $middleware->appendToPriorityList(\Illuminate\Routing\Middleware\SubstituteBindings::class, GuardAiRequests::class);

        $middleware->alias([
            'role' => CheckRole::class,
            'feature' => CheckFeatureEnabled::class,
            'image.limit' => CheckDailyImageLimit::class,
            'reminders.api' => \App\Http\Middleware\ReminderApiContext::class,
            'reminders.integration' => \App\Http\Middleware\AuthenticateReminderIntegration::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Keep the trace visible even when validation/authorization throws before
        // the controller can return JSON. Preserve list responses and HTTP status.
        $exceptions->respond(function ($response) {
            if ($id = request()->attributes->get('ai_interaction_id')) {
                $response->headers->set('X-Interaction-ID', $id);
                if ($response instanceof \Illuminate\Http\JsonResponse) {
                    $data = $response->getData(true);
                    if (is_array($data) && ! array_is_list($data)) {
                        $response->setData(array_merge($data, ['interaction_id' => $id]));
                    }
                }
            }
            return $response;
        });
        // Provider exceptions may embed request/response content and credentials.
        $exceptions->report(function (\GuzzleHttp\Exception\TransferException $e) {
            \App\Support\AiLog::error('Provider failure', ['status_code' => $e instanceof \GuzzleHttp\Exception\RequestException ? $e->getResponse()?->getStatusCode() : null]);
            return false;
        });
        $exceptions->report(function (\Illuminate\Http\Client\RequestException $e) {
            \App\Support\AiLog::error('Provider failure', ['status_code' => $e->response->status()]);
            return false;
        });
        $exceptions->render(function (\GuzzleHttp\Exception\TransferException $e) {
            return response()->json(['error' => 'No se pudo contactar al proveedor. Intenta de nuevo más tarde.'], 502);
        });
        $exceptions->render(function (\Illuminate\Http\Client\RequestException $e) {
            return response()->json(['error' => 'El proveedor no pudo completar la solicitud.'], 502);
        });
    })->create();
