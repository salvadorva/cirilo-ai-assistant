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
        Schema::create('static_audios', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['welcome', 'funny_phrase'])->comment('Tipo de audio estático');
            $table->text('text')->comment('Texto que se convierte a voz');
            $table->string('file_path')->nullable()->comment('Ruta del archivo MP3');
            $table->integer('index')->nullable()->comment('Índice para frases graciosas');
            $table->boolean('is_approved')->default(false)->comment('Si el audio ha sido aprobado por el admin');
            $table->boolean('is_active')->default(false)->comment('Si el audio está activo para uso');
            $table->integer('regeneration_count')->default(0)->comment('Veces que se ha regenerado');
            $table->timestamp('last_regenerated_at')->nullable()->comment('Última vez que se regeneró');
            $table->timestamps();

            // Índices
            $table->index(['type', 'is_approved', 'is_active']);
            $table->unique(['type', 'index']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('static_audios');
    }
};
