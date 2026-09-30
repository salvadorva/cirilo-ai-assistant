<?php

namespace App\Services\Tasks;

/**
 * F6-03 (flujo sin herramientas): «Guarda como pendiente: …» o «Tengo que …, todavía sin fecha»
 * crean un pendiente. Solo frases inequívocas; lo que tenga día u hora es de la agenda.
 */
class TaskIntent
{
    private const PATTERNS = [
        '/^(?:por favor,?\s*)?(?:guarda|guárdame|anota|anótame|apunta|apúntame|agrega|añade|registra)\s+(?:esto\s+)?(?:como\s+)?(?:un\s+)?pendiente(?:\s+sin\s+fecha)?\s*[:,\-]?\s*(?<title>.+)$/iu',
        '/^(?:pendiente|nuevo pendiente)\s*[:\-]\s*(?<title>.+)$/iu',
        '/^(?:tengo que|necesito|debo)\s+(?<title>.+?),?\s+(?:todav[ií]a\s+|a[uú]n\s+)?sin\s+fecha\.?$/iu',
    ];

    public static function capture(string $prompt): ?string
    {
        $prompt = trim($prompt);
        foreach (self::PATTERNS as $pattern) {
            if (preg_match($pattern, $prompt, $match)) {
                $title = trim(preg_replace('/\s*,?\s*(?:todav[ií]a\s+|a[uú]n\s+)?sin\s+fecha\.?$/iu', '', $match['title']), " \t.,;:");

                return $title !== '' && mb_strlen($title) <= 255 ? $title : null;
            }
        }

        return null;
    }
}
