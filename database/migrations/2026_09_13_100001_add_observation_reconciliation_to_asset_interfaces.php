<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Extend observed interface rows (asset_interfaces) with reconciliation to
     * the authoritative NetworkPort and an observation freshness marker.
     */
    public function up(): void
    {
        Schema::table('asset_interfaces', function (Blueprint $table) {
            $table->foreignId('reconciled_network_port_id')
                ->nullable()
                ->after('external_id')
                ->constrained('network_ports')
                ->onDelete('set null');

            // observed | stale — freshness of the provider observation.
            $table->string('observation_status', 16)
                ->default('observed')
                ->after('status');

            $table->index('reconciled_network_port_id');
            $table->index('observation_status');
        });
    }

    public function down(): void
    {
        Schema::table('asset_interfaces', function (Blueprint $table) {
            $table->dropIndex(['reconciled_network_port_id']);
            $table->dropIndex(['observation_status']);
            $table->dropForeign(['reconciled_network_port_id']);
            $table->dropColumn(['reconciled_network_port_id', 'observation_status']);
        });
    }
};
