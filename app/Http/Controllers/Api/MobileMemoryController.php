<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserProfileFact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gestión de la memoria de largo plazo (user_profile_facts).
 * El sistema extrae facts automáticamente al resumir conversaciones.
 * Estos endpoints permiten al usuario ver y borrar facts desde la app.
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
                'last_mentioned_at' => $f->last_mentioned_at?->toIso8601String(),
            ])->values();
        });

        return response()->json([
            'categories'      => UserProfileFact::CATEGORY_LABELS,
            'facts'           => $grouped,
            'total'           => $facts->count(),
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

        $fact->delete();

        return response()->json(['ok' => true]);
    }
}
