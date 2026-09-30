<?php

namespace App\Services\Reminders\Push;

/**
 * Resultado por destino. ACCEPTED significa aceptado por FCM para entrega,
 * no recibido ni leído en el teléfono.
 */
final class PushResult
{
    public const ACCEPTED = 'accepted';

    public const RETRYABLE = 'retryable';

    public const UNREGISTERED = 'unregistered';

    public const INVALID_PAYLOAD = 'invalid_payload';

    public const AUTH = 'auth';

    public const UNCERTAIN = 'uncertain';

    public const REJECTED = 'rejected';

    public function __construct(
        public readonly string $outcome,
        public readonly ?string $messageId = null,
        public readonly ?int $httpStatus = null,
        public readonly ?int $retryAfter = null,
        public readonly ?string $category = null,
    ) {}

    public static function accepted(string $messageId, int $status = 200): self
    {
        return new self(self::ACCEPTED, $messageId, $status);
    }

    public static function retryable(?int $status, string $category, ?int $retryAfter = null): self
    {
        return new self(self::RETRYABLE, null, $status, $retryAfter, $category);
    }

    public static function unregistered(?int $status = 404): self
    {
        return new self(self::UNREGISTERED, null, $status, null, 'unregistered');
    }

    public static function invalidPayload(?int $status = 400): self
    {
        return new self(self::INVALID_PAYLOAD, null, $status, null, 'invalid_argument');
    }

    public static function auth(?int $status = null, string $category = 'auth'): self
    {
        return new self(self::AUTH, null, $status, null, $category);
    }

    /** Timeout o caída después de enviar: FCM pudo haberlo aceptado. */
    public static function uncertain(string $category = 'timeout'): self
    {
        return new self(self::UNCERTAIN, null, null, null, $category);
    }

    public static function rejected(?int $status, string $category): self
    {
        return new self(self::REJECTED, null, $status, null, $category);
    }
}
