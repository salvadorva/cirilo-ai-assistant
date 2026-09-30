<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Registro idempotente de mutaciones de recordatorios por actor y clave.
 */
class ReminderCommand extends Model
{
    protected $fillable = ['actor_type', 'actor_id', 'idempotency_key', 'operation', 'contextual_reminder_id',
        'request_hash', 'response_status', 'response_body'];

    protected $casts = ['response_body' => 'array'];
}
