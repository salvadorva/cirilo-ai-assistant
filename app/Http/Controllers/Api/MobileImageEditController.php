<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Images\ImageEditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/** IE1: «Editar imagen» desde el clip del chat de la app. */
class MobileImageEditController extends Controller
{
    public function __construct(private ImageEditService $edits) {}

    /** POST /api/mobile/images/edit — multipart: image, instruction, conversation_id?; cabecera Idempotency-Key. */
    public function edit(Request $request): JsonResponse
    {
        if ($disabled = $this->disabled()) {
            return $disabled;
        }
        $data = $request->validate([
            'image' => 'required|file|mimes:jpg,jpeg,png,webp|max:5120',
            'instruction' => 'required|string|min:3|max:500',
            'conversation_id' => 'nullable|integer|min:1',
        ]);
        $key = $request->header('Idempotency-Key');
        if (! $this->validKey($key)) {
            return $this->missingKey();
        }
        $conversationId = isset($data['conversation_id'])
            ? $request->user()->conversations()->whereKey($data['conversation_id'])->value('id')
            : null;
        abort_if(isset($data['conversation_id']) && ! $conversationId, 404);

        return $this->edits->edit($request->user(), $data['image'], $data['instruction'], $conversationId, $key);
    }

    /** POST /api/mobile/images/edits/{id}/refine — JSON: instruction; cabecera Idempotency-Key. Ajusta el último resultado. */
    public function refine(Request $request, string $id): JsonResponse
    {
        if ($disabled = $this->disabled()) {
            return $disabled;
        }
        $data = $request->validate(['instruction' => 'required|string|min:3|max:500']);
        $key = $request->header('Idempotency-Key');
        if (! $this->validKey($key)) {
            return $this->missingKey();
        }

        return $this->edits->refine($request->user(), $id, $data['instruction'], $key);
    }

    /** GET /api/mobile/images/edits/{id} — solo su dueño, mientras no venza. */
    public function show(Request $request, string $id): Response
    {
        $edit = $this->edits->result($request->user(), $id);

        return response(Storage::disk(ImageEditService::DISK)->get($edit->path), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'no-store, private',
            'Content-Disposition' => 'inline; filename="cirilo-edicion.png"',
        ]);
    }

    private function disabled(): ?JsonResponse
    {
        return config('ai.image_edit.enabled') ? null
            : response()->json(['code' => 'image_edit_disabled', 'message' => 'La edición de imágenes aún no está disponible.'], 503);
    }

    private function validKey(mixed $key): bool
    {
        return is_string($key) && $key !== '' && strlen($key) <= 100;
    }

    private function missingKey(): JsonResponse
    {
        return response()->json(['code' => 'idempotency_key_required', 'message' => 'Falta la cabecera Idempotency-Key.'], 422);
    }
}
