<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\UserProfileFact;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * F4-04: recuperación por tema, fecha y acuerdos, sin búsqueda semántica.
 *
 * Complementa las conversaciones recientes con las anteriores que se relacionan con la pregunta
 * actual. Es deliberadamente simple (palabras clave, referencias de fecha e intención de «qué
 * acordamos»); la búsqueda semántica solo se justificaría si las evaluaciones lo muestran.
 */
class MemoryRetrieval
{
    public const MAX_CONVERSATIONS = 2;

    public const MAX_FACTS = 5;

    private const CANDIDATES = 300;

    private const DAYS = 365;

    private const MIN_SCORE = 2;

    private const STOPWORDS = ['aquella', 'aquel', 'algo', 'alguna', 'alguno', 'ahora', 'antes', 'ayer', 'anteayer', 'cada', 'como', 'cual',
        'cuales', 'cuando', 'cuanto', 'cuanta', 'cuantos', 'cuantas', 'desde', 'donde', 'dime', 'decir', 'esta', 'este', 'esto', 'estos',
        'estas', 'eres', 'hablamos', 'hablar', 'hace', 'hacer', 'hago', 'hasta', 'hola', 'otra', 'otro', 'para', 'pero', 'porque', 'puedes',
        'podemos', 'podrias', 'quiero', 'queria', 'saber', 'semana', 'pasada', 'pasado', 'sobre', 'tengo', 'tiene', 'tienes', 'todo', 'todos',
        'tema', 'temas', 'vamos', 'recuerdas', 'recuerda', 'acordamos', 'acordado', 'decidimos', 'quedamos', 'sesion', 'conversacion',
        'consejos', 'mejor', 'mejorar', 'ayuda', 'ayudame', 'favor', 'gracias', 'entonces', 'tambien', 'mucho', 'poco', 'bien', 'mismo'];

    private const AGREEMENT = '/acord|acuerd|decidi|decisi|quedamos|compromet|pendiente/u';

    /** Conversaciones anteriores relacionadas con la pregunta, excluyendo las ya incluidas. */
    public static function relatedConversations(int $userId, ?string $query, array $excludeIds): Collection
    {
        $keywords = self::keywords($query);
        $folded = self::fold((string) $query);
        $agreement = (bool) preg_match(self::AGREEMENT, $folded);
        $range = self::dateRange($folded);
        if ($keywords === [] && ! $agreement && ! $range) {
            return collect();
        }

        return Conversation::where('user_id', $userId)
            ->whereNotIn('id', array_filter($excludeIds))
            ->where('updated_at', '>=', now()->subDays(self::DAYS))
            ->orderByDesc('updated_at')
            ->limit(self::CANDIDATES)
            ->get()
            ->map(function (Conversation $conversation) use ($keywords, $agreement, $range) {
                $text = self::fold(implode(' ', array_merge([$conversation->title, $conversation->summary],
                    (array) $conversation->topics, (array) $conversation->decisions, (array) $conversation->pending_items)));
                $score = 0;
                foreach ($keywords as $keyword) {
                    if (preg_match('/\b'.preg_quote($keyword, '/').'/u', $text)) {
                        $score += 2;
                    }
                }
                if ($agreement && (! empty($conversation->decisions) || ! empty($conversation->pending_items))) {
                    $score += 2;
                }
                if ($range && ($conversation->updated_at->between(...$range) || $conversation->created_at?->between(...$range))) {
                    $score += 3;
                }
                $conversation->setAttribute('retrieval_score', $score);

                return $conversation;
            })
            ->filter(fn ($conversation) => $conversation->retrieval_score >= self::MIN_SCORE)
            ->sortByDesc(fn ($conversation) => [$conversation->retrieval_score, $conversation->updated_at->timestamp])
            ->take(self::MAX_CONVERSATIONS)
            ->values();
    }

    /** Hechos de intereses que la inyección por recencia deja fuera pero que tratan del tema preguntado. */
    public static function relatedFacts(int $userId, ?string $query, array $categories): Collection
    {
        $keywords = self::keywords($query);
        if ($keywords === []) {
            return collect();
        }

        return UserProfileFact::where('user_id', $userId)
            ->whereIn('category', $categories)
            ->where('confidence', '>=', MemoryService::MIN_CONFIDENCE)
            ->orderByDesc('confidence')
            ->get()
            ->filter(function (UserProfileFact $fact) use ($keywords) {
                $text = self::fold(str_replace('_', ' ', $fact->key).' '.$fact->value);
                foreach ($keywords as $keyword) {
                    if (preg_match('/\b'.preg_quote($keyword, '/').'/u', $text)) {
                        return true;
                    }
                }

                return false;
            })
            ->take(self::MAX_FACTS);
    }

    public static function keywords(?string $query): array
    {
        $words = preg_split('/[^a-z0-9]+/', self::fold((string) $query), -1, PREG_SPLIT_NO_EMPTY);
        $keywords = [];
        foreach ($words as $word) {
            if (mb_strlen($word) < 4 || in_array($word, self::STOPWORDS, true)) {
                continue;
            }
            // Plural simple: «bodas» también encuentra «boda».
            $keywords[] = mb_strlen($word) > 4 && str_ends_with($word, 's') ? mb_substr($word, 0, -1) : $word;
        }

        return array_values(array_unique($keywords));
    }

    private static function fold(string $text): string
    {
        return Str::ascii(mb_strtolower($text));
    }

    /** @return array{0: Carbon, 1: Carbon}|null */
    private static function dateRange(string $folded): ?array
    {
        return match (true) {
            (bool) preg_match('/\banteayer\b/', $folded) => [now()->subDays(2)->startOfDay(), now()->subDays(2)->endOfDay()],
            (bool) preg_match('/\bayer\b/', $folded) => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
            (bool) preg_match('/\bsemana pasada\b/', $folded) => [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()],
            (bool) preg_match('/\bmes pasado\b/', $folded) => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
            default => null,
        };
    }
}
