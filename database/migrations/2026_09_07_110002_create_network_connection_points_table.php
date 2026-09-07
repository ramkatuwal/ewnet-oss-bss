<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fail clearly if PostGIS is unavailable rather than falling back to non-spatial storage.
        $postgis = DB::selectOne("SELECT COUNT(*) AS c FROM pg_extension WHERE extname = 'postgis'");
        if (! $postgis || (int) $postgis->c < 1) {
            throw new RuntimeException('FIM-003a requires the PostGIS extension, which is not installed in this database.');
        }

        Schema::create('network_connection_points', function (Blueprint $table) {
            $table->id();
            $table->string('point_type', 50);
            $table->string('name')->nullable();
            $table->text('description')->nullable();

            // Identity anchors — exactly one of site_id, asset_id, or standalone geometry should be set.
            $table->foreignId('site_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('asset_interface_id')->nullable()->constrained('asset_interfaces')->nullOnDelete();

            // Standalone surveyed geometry (nullable — anchored points use site/asset location instead).
            $table->geometry('geometry', 'geometry')->nullable();

            // Organizational ownership (denormalized from site for query performance).
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();

            $table->string('status')->default('active');
            $table->jsonb('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('point_type');
            $table->index('site_id');
            $table->index('asset_id');
            $table->index('asset_interface_id');
            $table->index('company_id');
            $table->index('status');
            $table->index('created_by');
            $table->index('updated_by');
            $table->index('metadata');
        });

        // Spatial index for standalone point geometry queries.
        DB::statement('CREATE INDEX idx_ncp_geometry ON network_connection_points USING GIST (geometry)');
    }

    public function down(): void
    {
        Schema::dropIfExists('network_connection_points');
    }
};
