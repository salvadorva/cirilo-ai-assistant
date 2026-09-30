<?php

namespace App\Http\Controllers\Api\Integrations;

use App\Http\Controllers\Controller;
use App\Models\ContextualReminder;
use App\Models\ReminderIntegration;
use App\Services\Reminders\ContextualReminderService;
use App\Services\Reminders\ReminderApiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API dedicada de Hermes: /api/integrations/hermes/v1/reminders.
 * Propietario e integración salen de la credencial, nunca del body.
 */
class HermesReminderController extends Controller
{
    public function __construct(private ContextualReminderService $reminders) {}

    public function index(Request $request): JsonResponse
    {
        return $this->respond(200, $this->reminders->list($this->scoped($request), $request->query()));
    }

    public function show(Request $request, string $id): JsonResponse
    {
        return $this->respond(200, $this->reminders->detail($this->find($request, $id)));
    }

    public function store(Request $request): JsonResponse
    {
        [$status, $body, $replayed] = $this->reminders->create($this->integration($request), $request->json()->all(), $request->header('Idempotency-Key'));
        $response = $this->respond($status, $body, $replayed);
        if ($status === 201) {
            $response->headers->set('Location', url('/api/integrations/hermes/v1/reminders/'.$body['id']));
        }

        return $response;
    }

    public function complete(Request $request, string $id): JsonResponse
    {
        return $this->transition($request, $id, 'complete');
    }

    public function cancel(Request $request, string $id): JsonResponse
    {
        return $this->transition($request, $id, 'cancel');
    }

    public function snooze(Request $request, string $id): JsonResponse
    {
        return $this->transition($request, $id, 'snooze');
    }

    private function transition(Request $request, string $id, string $action): JsonResponse
    {
        $integration = $this->integration($request);
        [$status, $body, $replayed] = $this->reminders->transition($this->find($request, $id), $action, $request->json()->all(),
            ['integration', $integration->id], $request->header('Idempotency-Key'), 'integration');

        return $this->respond($status, $body, $replayed);
    }

    private function integration(Request $request): ReminderIntegration
    {
        return $request->attributes->get('reminder_integration');
    }

    private function scoped(Request $request)
    {
        $integration = $this->integration($request);

        return ContextualReminder::ownedBy($integration->user_id)->where('reminder_integration_id', $integration->id);
    }

    private function find(Request $request, string $id): ContextualReminder
    {
        return $this->scoped($request)->find($id)
            ?? throw new ReminderApiException(404, 'not_found', 'Recordatorio no encontrado.');
    }

    private function respond(int $status, array $body, bool $replayed = false): JsonResponse
    {
        $response = response()->json($body + ['server_time' => ContextualReminderService::utc(now())], $status);
        if ($replayed) {
            $response->headers->set('Idempotent-Replayed', 'true');
        }

        return $response;
    }
}
