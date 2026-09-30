<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Envío de un aviso de agenda (recordatorio previo o inicio) por un canal concreto (F3-01). */
class EventNotificationDelivery extends Model
{
    public const WAITING = ['pending', 'retry_wait'];

    protected $fillable = ['calendar_event_id', 'user_id', 'kind', 'channel', 'schedule_key', 'due_at', 'status', 'attempts', 'next_attempt_at',
        'claim_token', 'lease_expires_at', 'attempted_at', 'accepted_at', 'error_category', 'http_status'];

    protected $casts = ['due_at' => 'datetime', 'next_attempt_at' => 'datetime', 'lease_expires_at' => 'datetime',
        'attempted_at' => 'datetime', 'accepted_at' => 'datetime'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(CalendarEvent::class, 'calendar_event_id');
    }

    /** Texto para la interfaz: «aceptado» nunca significa «recibido» ni «leído». */
    public function label(): string
    {
        return match ($this->status) {
            'accepted' => $this->channel === 'internal' ? 'Guardado en tus notificaciones' : 'Aceptado por el proveedor',
            'pending' => 'Pendiente',
            'processing' => 'Enviando',
            'retry_wait' => 'Reintentando',
            'failed' => 'No se pudo enviar',
            'uncertain' => 'Resultado desconocido',
            'skipped' => match ($this->error_category) {
                'preference' => 'Omitido: canal desactivado',
                'quiet_hours' => 'Omitido: fuera del horario de correo',
                'cancelled' => 'Omitido: evento cancelado',
                'rescheduled' => 'Omitido: evento reprogramado',
                'expired' => 'Omitido: fuera de la ventana de envío',
                'legacy_notified' => 'Enviado por el sistema anterior',
                default => 'Omitido',
            },
            default => $this->status,
        };
    }
}
