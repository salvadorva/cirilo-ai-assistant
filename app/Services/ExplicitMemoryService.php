<?php

namespace App\Services;

use App\Models\UserProfileFact;

/**
 * Instrucciones explícitas de memoria del usuario (F4-08).
 *
 * El resumen/extracción solo corre con conversaciones de 4+ mensajes, así que un intercambio
 * breve como «Llámame Salva» nunca llegaba a user_profile_facts. Aquí se captura de forma
 * determinista, sin llamar al modelo, solo cuando la frase es inequívoca.
 */
class ExplicitMemoryService
{
    public const SOURCE = 'user_explicit';

    private const NAME_PATTERN = '/^(?:(?:por favor|mejor|oye|ok|bueno|ahora|desde ahora|de ahora en adelante)[,\s]+)*'
        .'(?:ll[áa]mame|puedes llamarme|prefiero que me llames|quiero que me llames|me gustar[íi]a que me llames)\s+'
        .'(?<name>[\p{L}\'’-]+(?:\s+[\p{L}\'’-]+){0,2})\s*(?:,?\s*por favor)?\s*[.!]*$/iu';

    // Palabras que indican que lo que sigue no es un nombre («llámame mañana», «llámame la atención»).
    private const NOT_NAME_WORDS = ['mañana', 'luego', 'después', 'despues', 'más', 'mas', 'tarde', 'temprano', 'cuando',
        'si', 'a', 'al', 'el', 'la', 'lo', 'los', 'las', 'un', 'una', 'por', 'para', 'en', 'hoy', 'ahora', 'pronto', 'ya',
        'otra', 'vez', 'de', 'del', 'y', 'o', 'que', 'con', 'esta', 'este', 'noche', 'semana', 'me', 'te', 'se', 'no'];

    private const CUE_PATTERN = '/(?<!\p{L})(?:ll[áa]mame|llamarme|me llames|recuerda que|acu[ée]rdate(?: de)? que|no olvides que|mi nombre es|me llamo|prefiero que)(?!\p{L})/iu';

    /** Guarda la preferencia si la frase es inequívoca. Devuelve el hecho guardado o null. */
    public static function capture(int $userId, ?string $prompt, ?int $conversationId = null): ?UserProfileFact
    {
        $name = self::preferredName((string) $prompt);
        if ($name === null) {
            return null;
        }

        MemoryService::unforget($userId, 'personal_info', 'preferred_name');

        return UserProfileFact::updateOrCreate(
            ['user_id' => $userId, 'category' => 'personal_info', 'key' => 'preferred_name'],
            ['value' => $name, 'confidence' => 1.0, 'source_type' => self::SOURCE, 'source_conversation_id' => $conversationId, 'last_mentioned_at' => now()]
        );
    }

    /** Indica si el texto del usuario contiene una instrucción explícita de memoria. */
    public static function hasCue(?string $text): bool
    {
        return (bool) preg_match(self::CUE_PATTERN, (string) $text);
    }

    private static function preferredName(string $prompt): ?string
    {
        $prompt = trim(preg_replace('/\s+/u', ' ', $prompt));
        if (mb_strlen($prompt) > 120 || ! preg_match(self::NAME_PATTERN, $prompt, $match)) {
            return null;
        }

        $words = explode(' ', $match['name']);
        foreach ($words as $word) {
            if (in_array(mb_strtolower($word), self::NOT_NAME_WORDS, true)) {
                return null;
            }
        }

        $name = implode(' ', array_map(
            fn ($word) => $word === mb_strtolower($word) ? mb_strtoupper(mb_substr($word, 0, 1)).mb_substr($word, 1) : $word,
            $words
        ));

        return mb_strlen($name) <= 60 ? $name : null;
    }
}
