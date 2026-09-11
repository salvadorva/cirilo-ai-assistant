<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FocusSlot;
use App\Services\TtsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CRUD de slots de mensajes de enfoque para la app móvil.
 * Los slots disparan push FCM (voz TTS o solo texto) vía focus:send-messages.
 */
class MobileFocusSlotController extends Controller
{
    /**
     * GET /api/mobile/focus-slots
     */
    public function index(Request $request): JsonResponse
    {
        $slots = FocusSlot::where('user_id', $request->user()->id)
            ->orderBy('time')
            ->get()
            ->map(fn (FocusSlot $s) => $this->format($s));

        return response()->json(['slots' => $slots]);
    }

    /**
     * POST /api/mobile/focus-slots
     */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validatePayload($request);
        $data['user_id'] = $request->user()->id;

        $slot = FocusSlot::create($data);

        return response()->json($this->format($slot), 201);
    }

    /**
     * PUT /api/mobile/focus-slots/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $slot = FocusSlot::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $slot) {
            return response()->json(['message' => 'No encontrado'], 404);
        }

        $slot->update($this->validatePayload($request, $partial = true));

        return response()->json($this->format($slot));
    }

    /**
     * DELETE /api/mobile/focus-slots/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $slot = FocusSlot::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $slot) {
            return response()->json(['message' => 'No encontrado'], 404);
        }

        $slot->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * POST /api/mobile/focus-slots/preview-audio
     * Genera el TTS del mensaje para escucharlo antes de guardar el slot.
     * El mp3 va a audio/dynamic (lo limpia audio:clean a los 7 días); el
     * audio definitivo del slot se cachea aparte en focus:send-messages.
     */
    public function previewAudio(Request $request, TtsService $tts): JsonResponse
    {
        $data = $request->validate([
            'message' => 'required|string|max:2000',
            'voice'   => 'sometimes|in:alloy,echo,fable,onyx,nova,shimmer',
        ]);

        $url = $tts->generateMp3($data['message'], $data['voice'] ?? 'echo');

        if (! $url) {
            return response()->json(['message' => 'No se pudo generar el audio'], 502);
        }

        return response()->json(['audio_url' => $url]);
    }

    private function validatePayload(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes|' : 'required|';

        return $request->validate([
            'title'      => $required.'string|max:255',
            'message'    => $required.'string|max:2000',
            'time'       => $required.'date_format:H:i',
            'days'       => $required.'array|min:1',
            'days.*'     => 'integer|between:1,7',
            'voice'      => 'sometimes|in:alloy,echo,fable,onyx,nova,shimmer',
            'with_audio' => 'sometimes|boolean',
            'enabled'    => 'sometimes|boolean',
        ]);
    }

    private function format(FocusSlot $slot): array
    {
        return [
            'id'           => $slot->id,
            'title'        => $slot->title,
            'message'      => $slot->message,
            'time'         => $slot->time,
            'days'         => array_values(array_map('intval', $slot->days ?? [])),
            'voice'        => $slot->voice,
            'with_audio'   => (bool) $slot->with_audio,
            'enabled'      => (bool) $slot->enabled,
            'last_sent_at' => $slot->last_sent_at?->toIso8601String(),
        ];
    }
}
