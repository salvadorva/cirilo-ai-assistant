<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F2-04/F2-05: borradores de agenda por conversación e idempotencia de operaciones. Aditiva.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Datos parciales de un evento mientras el chat pide lo que falta. Uno por usuario y ámbito
        // (conversación o sesión web); caduca solo, para no arrastrar intenciones viejas.
        Schema::create('agenda_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('scope_key', 100);
            $table->string('action', 20)->default('create');
            $table->json('data');
            $table->json('missing')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique(['user_id', 'scope_key']);
        });

        // Resultado de una operación identificada por Idempotency-Key: un reintento devuelve lo mismo.
        Schema::create('agenda_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('idempotency_key', 100);
            $table->string('scope', 30);
            $table->char('request_hash', 64);
            $table->string('status', 20);
            $table->json('event_ids')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'scope', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agenda_operations');
        Schema::dropIfExists('agenda_drafts');
    }
};
