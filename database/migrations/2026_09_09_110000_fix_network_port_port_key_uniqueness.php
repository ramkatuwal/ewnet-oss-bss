<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop the live-only partial unique index.
        DB::statement('DROP INDEX IF EXISTS network_ports_asset_port_key_unique');

        // Full UNIQUE constraint covering live + soft-deleted history.
        // A deleted port_key is permanently reserved for its Asset.
        Schema::table('network_ports', function ($table) {
            $table->unique(['asset_id', 'port_key'], 'network_ports_asset_port_key_unique');
        });
    }

    public function down(): void
    {
        Schema::table('network_ports', function ($table) {
            $table->dropUnique('network_ports_asset_port_key_unique');
        });

        DB::statement('CREATE UNIQUE INDEX network_ports_asset_port_key_unique ON network_ports (asset_id, port_key) WHERE deleted_at IS NULL');
    }
};
