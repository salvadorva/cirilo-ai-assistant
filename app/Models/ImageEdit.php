<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Edición de imagen (IE1): resultado privado con vencimiento; sin original ni instrucción. */
class ImageEdit extends Model
{
    use HasUuids;

    protected $fillable = ['user_id', 'conversation_id', 'idempotency_key', 'request_hash', 'status', 'path', 'error_code', 'expires_at'];

    protected $casts = ['expires_at' => 'datetime'];
}
