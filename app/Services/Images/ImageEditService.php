<?php

namespace App\Services\Images;

use App\Models\Conversation;
use App\Models\ImageEdit;
use App\Models\Message;
use App\Models\User;
use App\Services\ConversationHistory;
use App\Services\ImageQuotaService;
use App\Support\AiLog as Log;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * IE1: edita una imagen con el modelo de edición (gpt-image-1, /v1/images/edits).
 *
 * - Idempotencia: la misma clave devuelve el mismo resultado; con otra petición, conflicto.
 * - Privacidad: la imagen se reescribe (PNG, máx. 2048 px) y pierde EXIF/ubicación; el original
 *   nunca se guarda. El resultado queda en disco privado 7 días y solo lo ve su dueño.
 * - Cuota: la misma de generación (ImageQuotaService). Un rechazo no cuenta; un timeout sí
 *   (incierto: el proveedor pudo cobrar) y no se reintenta.
 * - Ajustes: cada resultado puede ajustarse sobre sí mismo hasta completar max_rounds imágenes
 *   por cadena (inicial + 2); Cirilo guía con un texto fijo cuántos ajustes quedan.
 */
class ImageEditService
{
    public const DISK = 'image_edits';

    public function __construct(private ImageQuotaService $quota) {}

    public function edit(User $user, UploadedFile $image, string $instruction, ?int $conversationId, string $key): JsonResponse
    {
        $hash = hash('sha256', hash_file('sha256', $image->getRealPath()).'|'.trim($instruction));

        try {
            $edit = ImageEdit::create(['user_id' => $user->id, 'conversation_id' => $conversationId, 'round' => 1,
                'idempotency_key' => $key, 'request_hash' => $hash, 'status' => 'pending']);
        } catch (QueryException) {
            return $this->replay(ImageEdit::where(['user_id' => $user->id, 'idempotency_key' => $key])->firstOrFail(), $hash);
        }

        $png = $this->reencode($image);
        if ($png === null) {
            return $this->fail($edit, 'failed', 'invalid_image', 422, 'No pude leer esa imagen. Prueba con otra foto.');
        }

        return $this->withQuota($edit, $user, fn () => $this->callProvider($edit, $user, $png, trim($instruction), '[Editar imagen] '.trim($instruction)));
    }

    /** Ajusta el último resultado de una cadena (sin volver a subir la foto). */
    public function refine(User $user, string $parentId, string $instruction, string $key): JsonResponse
    {
        $hash = hash('sha256', 'refine|'.$parentId.'|'.trim($instruction));
        if ($existing = ImageEdit::where(['user_id' => $user->id, 'idempotency_key' => $key])->first()) {
            return $this->replay($existing, $hash);
        }

        $parent = ImageEdit::where('user_id', $user->id)->findOrFail($parentId);
        $disk = Storage::disk(self::DISK);
        if ($parent->status === 'expired' || ($parent->expires_at && $parent->expires_at->isPast())) {
            return response()->json(['code' => 'image_expired', 'message' => 'Esa imagen ya venció. Adjunta la foto de nuevo para editarla.'], 410);
        }
        if ($parent->status !== 'completed' || ! $parent->path || ! $disk->exists($parent->path)) {
            return response()->json(['code' => 'not_refinable', 'message' => 'Esa imagen no se puede ajustar.'], 409);
        }
        if ($parent->round >= $this->maxRounds()) {
            return response()->json(['code' => 'edit_limit_reached', 'message' => 'Esa imagen ya tuvo todos sus ajustes. Adjunta una foto para empezar otra edición.'], 422);
        }
        if (ImageEdit::where('parent_id', $parent->id)->whereIn('status', ['pending', 'completed'])->exists()) {
            return response()->json(['code' => 'already_refined', 'message' => 'Esa imagen ya se ajustó; sigue desde la más reciente.'], 409);
        }

        try {
            $edit = ImageEdit::create(['user_id' => $user->id, 'conversation_id' => $parent->conversation_id, 'parent_id' => $parent->id,
                'round' => $parent->round + 1, 'idempotency_key' => $key, 'request_hash' => $hash, 'status' => 'pending']);
        } catch (QueryException) {
            return $this->replay(ImageEdit::where(['user_id' => $user->id, 'idempotency_key' => $key])->firstOrFail(), $hash);
        }

        $prompt = 'Mantén la imagen igual y cambia solo lo siguiente: '.trim($instruction);

        return $this->withQuota($edit, $user, fn () => $this->callProvider($edit, $user, (string) $disk->get($parent->path), $prompt, '[Ajustar imagen] '.trim($instruction)));
    }

    /** Texto fijo con el que Cirilo entrega cada imagen y dice cuántos ajustes quedan. */
    public function guide(int $round): string
    {
        $remaining = max(0, $this->maxRounds() - $round);

        return match (true) {
            $remaining === 0 => 'Esta es la última modificación que pude trabajarte.',
            $remaining === 1 => '¿Así está bien o cambio algo más? Me queda 1 ajuste.',
            $round === 1 => "¿Quedó como querías o cambio algo? Puedo hacerte {$remaining} ajustes más.",
            default => "¿Así está bien o cambio algo más? Me quedan {$remaining} ajustes.",
        };
    }

    private function maxRounds(): int
    {
        return max(1, (int) config('ai.image_edit.max_rounds', 3));
    }

    private function withQuota(ImageEdit $edit, User $user, \Closure $call): JsonResponse
    {
        try {
            return $this->quota->generate($user, $call);
        } catch (HttpResponseException $e) {
            // Límite diario o por minuto: no se envió nada al proveedor.
            $edit->update(['status' => 'failed', 'error_code' => 'image_quota_exceeded']);
            $data = $e->getResponse()->getData(true);

            return response()->json(['code' => 'image_quota_exceeded', 'message' => $data['error'] ?? 'Alcanzaste el límite de imágenes.',
                'limit' => $data['limit'] ?? null, 'used' => $data['used'] ?? null], 429);
        }
    }

    /** Resultado para su dueño mientras no venza. */
    public function result(User $user, string $id): ImageEdit
    {
        $edit = ImageEdit::where('user_id', $user->id)->findOrFail($id);
        abort_if($edit->status === 'expired' || ($edit->expires_at && $edit->expires_at->isPast()), 410, 'La imagen editada ya venció.');
        abort_unless($edit->status === 'completed' && $edit->path && Storage::disk(self::DISK)->exists($edit->path), 404);

        return $edit;
    }

    /** Borra resultados vencidos (retención de 7 días). */
    public function purgeExpired(): int
    {
        $count = 0;
        ImageEdit::where('status', 'completed')->where('expires_at', '<', now())->each(function (ImageEdit $edit) use (&$count) {
            $disk = Storage::disk(self::DISK);
            if ($edit->path && $disk->exists($edit->path) && ! $disk->delete($edit->path)) {
                // Sin permiso para borrar: se reintenta en la próxima purga en lugar de perder el rastro.
                Log::warning('ImageEdit: no se pudo borrar un resultado vencido', ['edit_id' => $edit->id]);

                return;
            }
            $edit->update(['status' => 'expired', 'path' => null]);
            $count++;
        });

        return $count;
    }

    private function callProvider(ImageEdit $edit, User $user, string $png, string $prompt, string $userText): JsonResponse
    {
        $config = config('ai.image_edit');
        try {
            $response = Http::withToken((string) config('services.openai.api_key'))
                ->timeout((int) $config['timeout_seconds'])
                ->attach('image', $png, 'imagen.png', ['Content-Type' => 'image/png'])
                ->post('https://api.openai.com/v1/images/edits', [
                    'model' => $config['model'],
                    'prompt' => $prompt,
                    'n' => 1,
                    'quality' => $config['quality'],
                    'size' => $config['size'],
                ]);
        } catch (ConnectionException $e) {
            Log::warning('ImageEdit: sin respuesta del proveedor', ['edit_id' => $edit->id]);

            return $this->fail($edit, 'uncertain', 'provider_timeout', 504, 'La edición tardó demasiado. No se reintentó para no cobrarla dos veces.');
        }

        if ($response->successful() && ($b64 = $response->json('data.0.b64_json'))) {
            $path = "images/edits/{$user->id}/{$edit->id}.png";
            Storage::disk(self::DISK)->put($path, base64_decode($b64));
            $this->shareWithGroup($path);
            $edit->update(['status' => 'completed', 'path' => $path, 'expires_at' => now()->addDays((int) $config['retention_days'])]);
            $conversation = $this->persistConversation($edit, $user, $userText);

            return response()->json($this->describe($edit) + ['conversation_id' => $conversation->id, 'image_url' => $this->url($edit)], 201);
        }

        $code = (string) $response->json('error.code');
        $message = strtolower((string) $response->json('error.message'));
        if ($response->status() === 400 && ($code === 'moderation_blocked' || $code === 'content_policy_violation' || str_contains($message, 'safety'))) {
            return $this->fail($edit, 'rejected', 'content_rejected', 422, 'No puedo hacer esa edición con esa imagen o instrucción. Prueba con otra.');
        }
        Log::warning('ImageEdit: el proveedor respondió error', ['edit_id' => $edit->id, 'status' => $response->status(), 'code' => $code]);

        return $this->fail($edit, 'failed', 'provider_error', $response->status() >= 500 ? 502 : 422, 'No se pudo editar la imagen. Intenta de nuevo.');
    }

    /**
     * El umask de PHP-FPM recorta los permisos de las carpetas nuevas; sin escritura de grupo el
     * scheduler (otro usuario del grupo) no podría borrar el resultado al vencer.
     */
    private function shareWithGroup(string $path): void
    {
        $disk = Storage::disk(self::DISK);
        for ($dir = dirname($path); $dir !== '.' && $dir !== 'images'; $dir = dirname($dir)) {
            @chmod($disk->path($dir), 02770);
        }
        @chmod($disk->path($path), 0660);
    }

    private function replay(ImageEdit $edit, string $hash): JsonResponse
    {
        if (! hash_equals($edit->request_hash, $hash)) {
            return response()->json(['code' => 'idempotency_conflict', 'message' => 'Esa clave ya se usó para otra edición.'], 409);
        }

        return match ($edit->status) {
            'completed' => response()->json($this->describe($edit) + ['conversation_id' => $edit->conversation_id, 'image_url' => $this->url($edit)]),
            'pending' => response()->json(['code' => 'in_progress', 'message' => 'La edición sigue en proceso.'], 409),
            'uncertain' => response()->json(['code' => 'provider_timeout', 'message' => 'La edición anterior quedó sin respuesta.'], 504),
            'rejected' => response()->json(['code' => 'content_rejected', 'message' => 'No puedo hacer esa edición con esa imagen o instrucción.'], 422),
            default => response()->json(['code' => $edit->error_code ?? 'failed', 'message' => 'No se pudo editar la imagen.'], 422),
        };
    }

    private function fail(ImageEdit $edit, string $status, string $code, int $http, string $message): JsonResponse
    {
        $edit->update(['status' => $status, 'error_code' => $code]);

        return response()->json(['code' => $code, 'message' => $message, 'id' => $edit->id], $http);
    }

    /** PNG sin metadatos, con el lado mayor limitado. Null si no es una imagen válida. */
    private function reencode(UploadedFile $file): ?string
    {
        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if (! $source) {
            return null;
        }
        $max = (int) config('ai.image_edit.max_side');
        [$w, $h] = [imagesx($source), imagesy($source)];
        $scale = min(1, $max / max($w, $h));
        if ($scale < 1) {
            $resized = imagescale($source, (int) round($w * $scale), (int) round($h * $scale));
            imagedestroy($source);
            $source = $resized;
        }
        ob_start();
        imagepng($source);
        imagedestroy($source);

        return (string) ob_get_clean();
    }

    private function persistConversation(ImageEdit $edit, User $user, string $userText): Conversation
    {
        $conversation = $edit->conversation_id ? Conversation::where('user_id', $user->id)->find($edit->conversation_id) : null;
        $conversation ??= Conversation::create(['user_id' => $user->id, 'title' => mb_substr(str_replace('[Editar imagen] ', 'Editar imagen: ', $userText), 0, 60),
            'type' => 'chat', 'content' => json_encode(['messages' => []])]);
        $label = $edit->parent_id ? '[Imagen ajustada: disponible 7 días en la app] ' : '[Imagen editada: disponible 7 días en la app] ';
        Message::create(['conversation_id' => $conversation->id, 'role' => 'user', 'content' => $userText]);
        Message::create(['conversation_id' => $conversation->id, 'role' => 'assistant', 'content' => $label.$this->guide($edit->round)]);
        ConversationHistory::refreshContent($conversation);
        $edit->update(['conversation_id' => $conversation->id]);

        return $conversation;
    }

    private function describe(ImageEdit $edit): array
    {
        return ['id' => $edit->id, 'url' => $this->url($edit), 'expires_at' => $edit->expires_at?->toIso8601String(),
            'round' => $edit->round, 'remaining' => max(0, $this->maxRounds() - $edit->round), 'assistant_message' => $this->guide($edit->round)];
    }

    private function url(ImageEdit $edit): string
    {
        return '/api/mobile/images/edits/'.$edit->id;
    }
}
