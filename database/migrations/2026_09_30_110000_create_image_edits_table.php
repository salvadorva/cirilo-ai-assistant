<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * IE1: ediciones de imagen. Guarda el resultado (privado, 7 días) y la clave de idempotencia;
 * nunca la imagen original ni la instrucción (queda solo como texto en la conversación).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('image_edits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('idempotency_key', 100);
            $table->char('request_hash', 64);
            // pending | completed | rejected | failed | uncertain | expired
            $table->string('status', 12)->default('pending');
            $table->string('path')->nullable();
            $table->string('error_code', 40)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'idempotency_key']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('image_edits');
    }
};
