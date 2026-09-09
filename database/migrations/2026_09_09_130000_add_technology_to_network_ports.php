<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('network_ports', function (Blueprint $table) {
            $table->string('technology', 20)->nullable()->after('port_direction');
        });

        DB::statement("
            ALTER TABLE network_ports ADD CONSTRAINT network_ports_technology_check
            CHECK (technology IS NULL OR technology IN ('gpon', 'epon', 'xgs-pon', '10g-epon'))
        ");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE network_ports DROP CONSTRAINT IF EXISTS network_ports_technology_check');

        Schema::table('network_ports', function (Blueprint $table) {
            $table->dropColumn('technology');
        });
    }
};
