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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('type'); // 'achievement', 'rank_loss', 'inactivity', 'daily_challenge', etc.
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable(); // datos adicionales específicos del tipo
            $table->boolean('is_read')->default(false);
            $table->boolean('is_important')->default(false); // para notificaciones prioritarias
            $table->string('icon')->nullable(); // icono de la notificación
            $table->string('color')->default('#007bff'); // color del badge
            $table->string('action_url')->nullable(); // URL a la que redirige la notificación
            $table->timestamp('expires_at')->nullable(); // fecha de expiración
            $table->timestamps();

            $table->index(['user_id', 'is_read']);
            $table->index(['type', 'created_at']);
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
