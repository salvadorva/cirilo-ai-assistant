<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Operación de agenda identificada por Idempotency-Key (F2-05). */
class AgendaOperation extends Model
{
    protected $fillable = ['user_id', 'idempotency_key', 'scope', 'request_hash', 'status', 'event_ids'];

    protected $casts = ['event_ids' => 'array'];
}
