<?php

namespace App\Services\Reminders;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Error estable de la API de recordatorios: {code, message, request_id, ...}.
 * Nunca incluye contexto del recordatorio ni errores crudos de terceros.
 */
class ReminderApiException extends RuntimeException
{
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
        public readonly array $extra = [],
        public readonly array $headers = [],
    ) {
        parent::__construct($message);
    }

    public static function validation(array $errors, string $code = 'validation_failed', string $message = 'Datos inválidos.'): self
    {
        return new self(422, $code, $message, ['errors' => $errors]);
    }

    public static function body(string $code, string $message, array $extra = []): array
    {
        return array_merge(['code' => $code, 'message' => $message, 'request_id' => request()->attributes->get('reminder_request_id')], $extra);
    }

    /** Error de contrato esperado: no se reporta como fallo de la aplicación. */
    public function report(): void {}

    public function render(): JsonResponse
    {
        return response()->json(self::body($this->errorCode, $this->getMessage(), $this->extra), $this->status, $this->headers);
    }
}
