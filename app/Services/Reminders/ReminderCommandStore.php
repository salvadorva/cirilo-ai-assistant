<?php

namespace App\Services\Reminders;

use App\Models\ReminderCommand;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Idempotencia de mutaciones por actor + clave. La restricción única reserva
 * la clave dentro de la misma transacción que la mutación: dos peticiones
 * simultáneas no ejecutan dos veces el efecto.
 */
class ReminderCommandStore
{
    public const KEY_PATTERN = '/^[A-Za-z0-9_\-:.]{16,100}$/';

    /**
     * @param  Closure(): array{0: int, 1: array}  $mutation  Resultado a guardar; lanzar ReminderApiException para no guardarlo.
     * @return array{0: int, 1: array, 2: bool} status, body, replayed
     */
    public function run(string $actorType, int $actorId, ?string $key, string $operation, ?string $reminderId, array $payload, Closure $mutation): array
    {
        if ($key === null || ! preg_match(self::KEY_PATTERN, $key)) {
            throw new ReminderApiException(422, 'idempotency_key_required', 'Se requiere un encabezado Idempotency-Key aleatorio (16–100 caracteres).');
        }
        $scope = ['actor_type' => $actorType, 'actor_id' => $actorId, 'idempotency_key' => $key];
        $hash = hash('sha256', json_encode([$operation, $reminderId, $this->normalize($payload)]));

        if ($existing = ReminderCommand::where($scope)->first()) {
            return $this->replay($existing, $hash);
        }

        try {
            return DB::transaction(function () use ($scope, $operation, $reminderId, $hash, $mutation) {
                $command = ReminderCommand::create($scope + ['operation' => $operation, 'contextual_reminder_id' => $reminderId, 'request_hash' => $hash]);
                [$status, $body] = $mutation();
                $command->update(['response_status' => $status, 'response_body' => $body, 'contextual_reminder_id' => $body['id'] ?? $reminderId]);

                return [$status, $body, false];
            });
        } catch (UniqueConstraintViolationException) {
            // Otra petición con la misma clave ganó la reserva.
            $existing = ReminderCommand::where($scope)->first();

            return $existing ? $this->replay($existing, $hash) : throw $this->conflict();
        }
    }

    private function replay(ReminderCommand $command, string $hash): array
    {
        if (! hash_equals($command->request_hash, $hash) || $command->response_status === null) {
            throw $this->conflict();
        }

        return [$command->response_status, $command->response_body, true];
    }

    private function conflict(): ReminderApiException
    {
        return new ReminderApiException(409, 'idempotency_conflict', 'La clave de idempotencia ya se usó con otra petición o sigue en curso.');
    }

    private function normalize(array $payload): array
    {
        ksort($payload);

        return array_map(fn ($value) => is_array($value) ? $this->normalize($value) : $value, $payload);
    }
}
