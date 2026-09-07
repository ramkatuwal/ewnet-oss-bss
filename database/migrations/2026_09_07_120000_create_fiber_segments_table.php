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
            throw new RuntimeException('FIM-003b requires the PostGIS extension, which is not installed in this database.');
        }

        Schema::create('fiber_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiber_cable_id')->constrained('fiber_cables')->restrictOnDelete();
            $table->foreignId('endpoint_a_id')->constrained('network_connection_points')->restrictOnDelete();
            $table->foreignId('endpoint_b_id')->constrained('network_connection_points')->restrictOnDelete();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->unsignedInteger('sequence');
            $table->decimal('length_meters', 12, 2)->nullable();
            $table->string('status', 50);
            $table->jsonb('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['fiber_cable_id', 'sequence']);
            $table->index('endpoint_a_id');
            $table->index('endpoint_b_id');
            $table->index('company_id');
            $table->index('status');
        });

        DB::statement('ALTER TABLE fiber_segments ADD COLUMN geometry geometry(LineString,4326) NULL');
        DB::statement('ALTER TABLE fiber_segments ADD CONSTRAINT fiber_segments_distinct_endpoints CHECK (endpoint_a_id <> endpoint_b_id)');
        DB::statement('ALTER TABLE fiber_segments ADD CONSTRAINT fiber_segments_sequence_positive CHECK (sequence > 0)');
        DB::statement('ALTER TABLE fiber_segments ADD CONSTRAINT fiber_segments_geometry_not_empty CHECK (geometry IS NULL OR NOT ST_IsEmpty(geometry))');
        DB::statement('CREATE INDEX fiber_segments_geometry_gist ON fiber_segments USING GIST (geometry)');
    }

    public function down(): void
    {
        Schema::dropIfExists('fiber_segments');
    }
};
