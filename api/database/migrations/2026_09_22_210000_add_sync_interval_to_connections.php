<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            // How often this connection should be synced by the per-connection runner.
            $table->unsignedInteger('sync_interval_seconds')->default(60)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('sync_interval_seconds');
        });
    }
};
