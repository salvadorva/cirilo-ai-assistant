<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContextualReminder;
use App\Services\Reminders\ContextualReminderAudio;
use App\Services\Reminders\ContextualReminderService;
use App\Services\Reminders\ReminderApiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Recordatorios contextuales para la app Android (Sanctum). Mismas reglas de
 * dominio, idempotencia y versión que Hermes; la app no crea recordatorios.
 */
class MobileContextualReminderController extends Controller
{
    public function __construct(private ContextualReminderService $reminders) {}

    /**
     * GET /api/mobile/contextual-reminders
     */
    public function index(Request $request): JsonResponse
    {
        return $this->respond(200, $this->reminders->list(ContextualReminder::ownedBy($request->user()->id), $request->query()));
    }

    /**
     * GET /api/mobile/contextual-reminders/{id}
     */
    public function show(Request $request, string $id): JsonResponse
    {
        return $this->respond(200, $this->reminders->detail($this->find($request, $id)));
    }

    /**
     * POST /api/mobile/contextual-reminders/{id}/{complete|cancel|snooze}
     */
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

    /**
     * POST /api/mobile/contextual-reminders/{id}/receipts
     * Recibo received/displayed para diagnóstico; nunca completa la tarea.
     */
    public function receipt(Request $request, string $id): JsonResponse
    {
        return $this->respond(200, $this->reminders->recordReceipt($this->find($request, $id), $request->user()->id, $request->json()->all()));
    }

    /**
     * GET /api/mobile/contextual-reminders/{id}/audio
     * Audio privado del propietario, solo mientras el recordatorio sigue pendiente.
     */
    public function audio(Request $request, string $id, ContextualReminderAudio $audio): StreamedResponse
    {
        $reminder = $this->find($request, $id);
        if ($reminder->effectiveState() !== ContextualReminder::PENDING || ! $audio->ready($reminder)) {
            throw new ReminderApiException(404, 'audio_not_available', 'No hay audio disponible; el recordatorio funciona como texto.');
        }

        return Storage::disk(ContextualReminderAudio::DISK)->response($reminder->audio_path, 'recordatorio.mp3',
            ['Content-Type' => 'audio/mpeg', 'Cache-Control' => 'private, no-store']);
    }

    private function transition(Request $request, string $id, string $action): JsonResponse
    {
        // Ámbito de idempotencia: propietario autenticado (Sanctum), no la instalación.
        // Decisión RC3: installation_id lo declara el cliente; el usuario del token no.
        // Con claves aleatorias el ámbito por usuario es igual de seguro y más estricto.
        [$status, $body, $replayed] = $this->reminders->transition($this->find($request, $id), $action, $request->json()->all(),
            ['user', $request->user()->id], $request->header('Idempotency-Key'), 'mobile');

        return $this->respond($status, $body, $replayed);
    }

    private function find(Request $request, string $id): ContextualReminder
    {
        return ContextualReminder::ownedBy($request->user()->id)->find($id)
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
