<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $postgis = DB::selectOne("SELECT COUNT(*) AS c FROM pg_extension WHERE extname = 'postgis'");
        if (! $postgis || (int) $postgis->c < 1) {
            throw new RuntimeException('FIM-003a requires the PostGIS extension, which is not installed in this database.');
        }

        Schema::table('sites', function (Blueprint $table) {
            $table->geometry('geometry', 'geometry')->nullable()->after('altitude');
        });

        DB::statement('CREATE INDEX idx_sites_geometry ON sites USING GIST (geometry)');
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropIndex('idx_sites_geometry');
            $table->dropColumn('geometry');
        });
    }
};
