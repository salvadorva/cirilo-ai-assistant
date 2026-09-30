<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use App\Support\AiLog as Log;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * F4-01: la tabla messages es la fuente principal del historial; conversations.content es una copia
 * derivada que se reconstruye desde ella (la leen la web, el resumen y la memoria).
 *
 * La web envía la lista completa de su sesión en cada guardado. Se agregan solo los mensajes que no
 * existen (emparejando en orden por rol y contenido), así que guardar dos veces no duplica y una
 * pestaña con una copia vieja no borra lo que se agregó desde el teléfono.
 */
class ConversationHistory
{
    public const ROLES = ['user', 'assistant'];

    /** Incorpora los mensajes que envía un cliente y devuelve el historial canónico. */
    public static function mergeFromClient(Conversation $conversation, array $incoming): array
    {
        return DB::transaction(function () use ($conversation, $incoming) {
            Conversation::whereKey($conversation->id)->lockForUpdate()->first();
            $existing = $conversation->messages()->orderBy('id')->get(['role', 'content'])->all();

            $added = 0;
            $diverged = false;
            $cursor = 0;
            foreach (self::normalize($incoming) as $message) {
                $found = null;
                for ($i = $cursor; $i < count($existing); $i++) {
                    if ($existing[$i]->role === $message['role'] && $existing[$i]->content === $message['content']) {
                        $found = $i;
                        break;
                    }
                }
                if ($found !== null) {
                    $diverged = $diverged || $found !== $cursor;
                    $cursor = $found + 1;

                    continue;
                }
                $diverged = $diverged || $cursor < count($existing);
                self::insert($conversation, $message);
                $added++;
            }

            if ($diverged && $added > 0) {
                Log::warning('ConversationHistory: guardado divergente; se agregaron mensajes al final', [
                    'conversation_id' => $conversation->id, 'added' => $added,
                ]);
            }

            return self::refreshContent($conversation);
        });
    }

    /** Reconstruye conversations.content desde la tabla messages. */
    public static function refreshContent(Conversation $conversation): array
    {
        $messages = self::messages($conversation);
        $conversation->content = json_encode(['messages' => $messages], JSON_UNESCAPED_UNICODE);
        $conversation->save();

        return $messages;
    }

    public static function messages(Conversation $conversation): array
    {
        return $conversation->messages()->orderBy('id')->get()->map(fn (Message $m) => array_filter([
            'role' => $m->role,
            'content' => $m->content,
            'timestamp' => $m->created_at?->toIso8601String(),
            'image_path' => $m->image_path,
        ], fn ($value) => $value !== null))->all();
    }

    /** Solo mensajes con rol permitido y contenido de texto. */
    public static function normalize(array $incoming): array
    {
        $messages = [];
        foreach ($incoming as $message) {
            if (is_array($message) && in_array($message['role'] ?? null, self::ROLES, true) && is_string($message['content'] ?? null)) {
                $messages[] = $message;
            }
        }

        return $messages;
    }

    public static function insert(Conversation $conversation, array $message): Message
    {
        $row = new Message([
            'conversation_id' => $conversation->id,
            'role' => $message['role'],
            'content' => $message['content'],
            'image_path' => is_string($message['image_path'] ?? null) ? $message['image_path'] : null,
        ]);
        $at = self::timestamp($message['timestamp'] ?? null);
        if ($at) {
            $row->created_at = $at;
            $row->updated_at = $at;
        }
        $row->save();

        return $row;
    }

    /** Conserva la hora que envió el cliente si es válida y no está en el futuro. */
    private static function timestamp(mixed $value): ?Carbon
    {
        if (! is_string($value) || $value === '') {
            return null;
        }
        try {
            $at = Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }

        return $at->isAfter(now()) ? null : $at->setTimezone(config('app.timezone'));
    }
}
