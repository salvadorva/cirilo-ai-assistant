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
        if (! config('ai.image_edit.enabled')) {
            return response()->json(['code' => 'image_edit_disabled', 'message' => 'La edición de imágenes aún no está disponible.'], 503);
        }
        $data = $request->validate([
            'image' => 'required|file|mimes:jpg,jpeg,png,webp|max:5120',
            'instruction' => 'required|string|min:3|max:500',
            'conversation_id' => 'nullable|integer|min:1',
        ]);
        $key = $request->header('Idempotency-Key');
        if (! is_string($key) || $key === '' || strlen($key) > 100) {
            return response()->json(['code' => 'idempotency_key_required', 'message' => 'Falta la cabecera Idempotency-Key.'], 422);
        }
        $conversationId = isset($data['conversation_id'])
            ? $request->user()->conversations()->whereKey($data['conversation_id'])->value('id')
            : null;
        abort_if(isset($data['conversation_id']) && ! $conversationId, 404);

        return $this->edits->edit($request->user(), $data['image'], $data['instruction'], $conversationId, $key);
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
}
