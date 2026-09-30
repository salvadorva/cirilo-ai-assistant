<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RC1 — recordatorios contextuales. Migración aditiva: no toca focus_slots,
 * device_tokens ni ninguna tabla existente. Fechas guardadas en UTC.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminder_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 50);
            // Solo hash SHA-256 de la credencial opaca; rotarla no cambia la identidad.
            $table->char('token_hash', 64)->unique();
            $table->json('scopes');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('contextual_reminders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reminder_integration_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 120);
            $table->text('context');
            $table->text('next_action');
            $table->dateTime('scheduled_at');
            $table->dateTime('expires_at');
            $table->string('timezone', 64);
            $table->boolean('with_audio')->default(false);
            $table->string('voice', 20)->nullable();
            $table->string('audio_path')->nullable();
            $table->string('state', 20)->default('pending');
            $table->unsignedInteger('version')->default(1);
            $table->boolean('confirmed_by_user');
            $table->dateTime('confirmed_at');
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->dateTime('expired_at')->nullable();
            $table->string('closed_by', 20)->nullable();
            $table->timestamps();

            $table->index(['state', 'scheduled_at']);
            $table->index(['state', 'expires_at']);
            $table->index(['user_id', 'state']);
        });

        Schema::create('reminder_commands', function (Blueprint $table) {
            $table->id();
            $table->string('actor_type', 20);
            $table->unsignedBigInteger('actor_id');
            $table->string('idempotency_key', 100);
            $table->string('operation', 20);
            $table->uuid('contextual_reminder_id')->nullable();
            $table->char('request_hash', 64);
            $table->unsignedSmallInteger('response_status')->nullable();
            // Resultado mínimo confirmado; nunca contexto ni siguiente acción.
            $table->json('response_body')->nullable();
            $table->timestamps();

            $table->unique(['actor_type', 'actor_id', 'idempotency_key']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_commands');
        Schema::dropIfExists('contextual_reminders');
        Schema::dropIfExists('reminder_integrations');
    }
};
