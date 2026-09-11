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
        Schema::table('calendar_events', function (Blueprint $table) {
            $table->boolean('nextcloud_synced')->default(false)->after('notified');
            $table->string('nextcloud_uid', 120)->nullable()->after('nextcloud_synced');
        });
    }

    public function down(): void
    {
        Schema::table('calendar_events', function (Blueprint $table) {
            $table->dropColumn(['nextcloud_synced', 'nextcloud_uid']);
        });
    }
};
