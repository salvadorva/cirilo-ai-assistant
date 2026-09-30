<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auditoría de reclamos y liberaciones de instalaciones. Aditiva. Sin tokens,
 * IP ni user-agent (política F0); los IDs sobreviven al borrado del dispositivo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_installation_claims', function (Blueprint $table) {
            $table->id();
            $table->string('installation_id', 100);
            $table->unsignedBigInteger('device_token_id')->nullable();
            $table->unsignedBigInteger('from_user_id')->nullable();
            $table->unsignedBigInteger('to_user_id')->nullable();
            // transferred | already_owned | denied_possession | not_found | rate_limited | operator_release
            $table->string('outcome', 30);
            $table->string('reason', 200)->nullable();
            $table->timestamps();

            $table->index('installation_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_installation_claims');
    }
};
