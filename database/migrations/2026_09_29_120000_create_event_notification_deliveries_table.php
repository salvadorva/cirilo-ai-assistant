<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F3-01: un registro por evento, tipo de aviso, canal y versión del horario. Aditiva: el indicador
 * legado calendar_events.notified se conserva (y se sigue actualizando) por compatibilidad.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendar_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 10);          // reminder | start
            $table->string('channel', 10);       // internal | email | telegram | fcm
            $table->char('schedule_key', 16);    // cambia si se reprograma el evento
            $table->dateTime('due_at');
            // pending | processing | retry_wait | accepted | failed | skipped | uncertain
            $table->string('status', 12)->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->dateTime('next_attempt_at')->nullable();
            $table->uuid('claim_token')->nullable();
            $table->dateTime('lease_expires_at')->nullable();
            $table->dateTime('attempted_at')->nullable();
            $table->dateTime('accepted_at')->nullable();
            $table->string('error_category', 30)->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->timestamps();

            $table->unique(['calendar_event_id', 'kind', 'channel', 'schedule_key'], 'event_notification_unique');
            $table->index(['status', 'next_attempt_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_notification_deliveries');
    }
};
