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
            throw new RuntimeException('FIM-002 requires the PostGIS extension, which is not installed in this database.');
        }

        Schema::create('fiber_cables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('cable_code', 100);
            $table->string('name');
            $table->string('cable_type', 50);
            $table->unsignedInteger('fiber_count');
            $table->string('status', 50);
            $table->foreignId('start_site_id')->nullable()->constrained('sites')->nullOnDelete();
            $table->foreignId('end_site_id')->nullable()->constrained('sites')->nullOnDelete();
            // Authoritative route geometry is added below as PostGIS geometry(LineString,4326).
            $table->decimal('length_meters', 12, 2)->nullable();
            $table->date('installation_date')->nullable();
            $table->string('survey_source', 100)->nullable();
            $table->timestamp('surveyed_at')->nullable();
            $table->foreignId('surveyed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->jsonb('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Identity: cable code is unique within the owning company, including soft-deleted rows.
            $table->unique(['company_id', 'cable_code']);

            // B-tree filtering indexes (FK columns are indexed explicitly).
            $table->index('company_id');
            $table->index('status');
            $table->index('cable_type');
            $table->index('start_site_id');
            $table->index('end_site_id');
        });

        // PostGIS authoritative route geometry. Typmod enforces LineString + SRID 4326.
        DB::statement('ALTER TABLE fiber_cables ADD COLUMN route_geometry geometry(LineString,4326) NOT NULL');

        // Backstop integrity: positive fiber count and non-empty geometry.
        DB::statement('ALTER TABLE fiber_cables ADD CONSTRAINT fiber_cables_fiber_count_positive CHECK (fiber_count > 0)');
        DB::statement('ALTER TABLE fiber_cables ADD CONSTRAINT fiber_cables_route_geometry_not_empty CHECK (NOT ST_IsEmpty(route_geometry))');

        // Spatial index for route queries.
        DB::statement('CREATE INDEX fiber_cables_route_geometry_gist ON fiber_cables USING GIST (route_geometry)');
    }

    public function down(): void
    {
        Schema::dropIfExists('fiber_cables');
    }
};
