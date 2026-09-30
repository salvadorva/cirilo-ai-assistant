<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RC2 — despacho durable. Aditiva: crea reminder_deliveries y añade columnas
 * nullable a device_tokens sin alterar las existentes ni sus datos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_tokens', function (Blueprint $table) {
            // Identidad estable de instalación y capacidades (las registra Android en RC3).
            $table->string('installation_id', 100)->nullable()->after('platform');
            $table->json('capabilities')->nullable()->after('installation_id');
            $table->string('app_version', 40)->nullable()->after('capabilities');
            // Token no registrado según FCM: se deshabilita, no se borra.
            $table->timestamp('disabled_at')->nullable()->after('last_used_at');
        });

        Schema::create('reminder_deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('source_type', 20); // contextual | routine
            $table->uuid('contextual_reminder_id')->nullable();
            $table->foreignId('focus_slot_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('occurrence_key', 100);
            $table->unsignedInteger('revision');
            $table->foreignId('device_token_id')->nullable()->constrained()->nullOnDelete();
            $table->string('destination_key', 50);
            $table->dateTime('scheduled_at');
            $table->dateTime('expires_at');
            // pending | processing | retry_wait | accepted | failed | uncertain | skipped
            $table->string('status', 20)->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('uncertain_count')->default(0);
            $table->uuid('claim_token')->nullable();
            $table->dateTime('lease_expires_at')->nullable();
            $table->dateTime('in_flight_since')->nullable();
            $table->dateTime('enqueued_at')->nullable();
            $table->dateTime('attempted_at')->nullable();
            $table->dateTime('next_attempt_at')->nullable();
            $table->dateTime('accepted_at')->nullable();
            $table->string('provider_message_id', 200)->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('error_category', 40)->nullable();
            $table->timestamps();

            $table->foreign('contextual_reminder_id')->references('id')->on('contextual_reminders')->cascadeOnDelete();
            $table->unique(['source_type', 'occurrence_key', 'revision', 'destination_key'], 'reminder_deliveries_occurrence_unique');
            $table->index(['status', 'next_attempt_at']);
            $table->index(['status', 'lease_expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_deliveries');
        Schema::table('device_tokens', function (Blueprint $table) {
            $table->dropColumn(['installation_id', 'capabilities', 'app_version', 'disabled_at']);
        });
    }
};
