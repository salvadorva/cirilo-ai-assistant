<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserProfileFact;
use App\Services\MemoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gestión de la memoria de largo plazo (user_profile_facts).
 * El sistema extrae facts automáticamente al resumir conversaciones.
 * Estos endpoints permiten al usuario ver, editar, olvidar y desactivar la extracción (F4-06).
 * Lo olvidado queda como lápida y una extracción posterior no lo revive.
 */
class MobileMemoryController extends Controller
{
    /**
     * GET /api/mobile/memory
     * Devuelve facts agrupados por categoría, ordenados por confidence descendente.
     */
    public function index(Request $request): JsonResponse
    {
        $facts = UserProfileFact::where('user_id', $request->user()->id)
            ->orderByDesc('confidence')
            ->orderByDesc('last_mentioned_at')
            ->get();

        $grouped = $facts->groupBy('category')->map(function ($items) {
            return $items->map(fn (UserProfileFact $f) => [
                'id'                => $f->id,
                'key'               => $f->key,
                'value'             => $f->value,
                'confidence'        => (float) $f->confidence,
                'source_type'       => $f->source_type,
                'source_conversation_id' => $f->source_conversation_id,
                'last_mentioned_at' => $f->last_mentioned_at?->toIso8601String(),
            ])->values();
        });

        return response()->json([
            'categories'      => UserProfileFact::CATEGORY_LABELS,
            'facts'           => $grouped,
            'total'           => $facts->count(),
            'extraction_enabled' => (bool) $request->user()->memory_extraction_enabled,
        ]);
    }

    /**
     * DELETE /api/mobile/memory/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $fact = UserProfileFact::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $fact) {
            return response()->json(['message' => 'No encontrado'], 404);
        }

        MemoryService::forgetFact($fact);

        return response()->json(['ok' => true]);
    }

    /**
     * PATCH /api/mobile/memory/{id}  { value }
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['value' => 'required|string|max:500']);
        $fact = UserProfileFact::where('id', $id)->where('user_id', $request->user()->id)->first();

        if (! $fact) {
            return response()->json(['message' => 'No encontrado'], 404);
        }

        $fact = MemoryService::editFact($fact, trim($data['value']));

        return response()->json(['ok' => true, 'fact' => [
            'id'          => $fact->id,
            'key'         => $fact->key,
            'value'       => $fact->value,
            'confidence'  => (float) $fact->confidence,
            'source_type' => $fact->source_type,
        ]]);
    }

    /**
     * DELETE /api/mobile/memory — olvida todos los hechos del usuario.
     */
    public function clear(Request $request): JsonResponse
    {
        MemoryService::forgetAll($request->user()->id);

        return response()->json(['ok' => true]);
    }

    /**
     * PUT /api/mobile/memory/settings  { extraction_enabled }
     */
    public function settings(Request $request): JsonResponse
    {
        $data = $request->validate(['extraction_enabled' => 'required|boolean']);
        $request->user()->forceFill(['memory_extraction_enabled' => $data['extraction_enabled']])->save();

        return response()->json(['ok' => true, 'extraction_enabled' => (bool) $data['extraction_enabled']]);
    }
}
