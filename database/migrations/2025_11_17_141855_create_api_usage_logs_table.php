<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('api_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('api_provider')->default('openai'); // openai, grok, etc.
            $table->string('api_type')->index(); // text_generation, image_generation, tts, stt, image_analysis
            $table->string('model')->nullable(); // gpt-4o, dall-e-3, tts-1, etc.
            $table->text('prompt')->nullable(); // Prompt usado (opcional)
            $table->integer('prompt_tokens')->default(0);
            $table->integer('completion_tokens')->default(0);
            $table->integer('total_tokens')->default(0);
            $table->decimal('estimated_cost', 10, 6)->default(0); // Costo estimado en USD
            $table->string('status')->default('success'); // success, error, timeout
            $table->text('error_message')->nullable();
            $table->integer('response_time_ms')->nullable(); // Tiempo de respuesta en milisegundos
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->json('metadata')->nullable(); // Datos adicionales específicos
            $table->timestamps();

            // Índices para optimizar queries
            $table->index(['user_id', 'api_type']);
            $table->index(['api_provider', 'api_type']);
            $table->index(['created_at']);
            $table->index(['status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_usage_logs');
    }
};
