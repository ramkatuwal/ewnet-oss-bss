<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('network_connection_points', function (Blueprint $table) {
            $table->foreignId('network_port_id')->nullable()->after('asset_interface_id')->constrained('network_ports')->nullOnDelete();
        });

        // XOR constraint: NCP may reference AssetInterface OR NetworkPort, but not both.
        DB::statement('
            ALTER TABLE network_connection_points ADD CONSTRAINT ncp_asset_interface_or_network_port_check
            CHECK (
                NOT (asset_interface_id IS NOT NULL AND network_port_id IS NOT NULL)
            )
        ');

        Schema::table('network_connection_points', function (Blueprint $table) {
            $table->index('network_port_id');
        });
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE network_connection_points DROP CONSTRAINT IF EXISTS ncp_asset_interface_or_network_port_check');

        Schema::table('network_connection_points', function (Blueprint $table) {
            $table->dropForeign(['network_port_id']);
            $table->dropIndex('idx_ncp_network_port_id');
            $table->dropColumn('network_port_id');
        });
    }
};
