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
        Schema::table('users', function (Blueprint $table) {
            $table->string('nextcloud_url')->nullable()->after('telegram_chat_id');
            $table->string('nextcloud_username')->nullable()->after('nextcloud_url');
            $table->text('nextcloud_password')->nullable()->after('nextcloud_username');
            $table->string('nextcloud_calendar', 100)->nullable()->default('personal')->after('nextcloud_password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['nextcloud_url', 'nextcloud_username', 'nextcloud_password', 'nextcloud_calendar']);
        });
    }
};
