<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add freshness marker to observed IP address rows so providers that
     * stop reporting an address can be surfaced as stale (never deleted).
     */
    public function up(): void
    {
        Schema::table('ip_addresses', function (Blueprint $table) {
            // observed | stale
            $table->string('observation_status', 16)
                ->default('observed')
                ->after('is_management');

            $table->index('observation_status');
        });
    }

    public function down(): void
    {
        Schema::table('ip_addresses', function (Blueprint $table) {
            $table->dropIndex(['observation_status']);
            $table->dropColumn(['observation_status']);
        });
    }
};
