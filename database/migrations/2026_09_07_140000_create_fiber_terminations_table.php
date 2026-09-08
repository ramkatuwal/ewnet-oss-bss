<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE fiber_cores ADD CONSTRAINT fiber_cores_id_company_unique UNIQUE (id, company_id)');

        Schema::create('fiber_terminations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiber_core_id')->constrained('fiber_cores')->restrictOnDelete();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('network_connection_point_id')->constrained('network_connection_points')->restrictOnDelete();
            $table->char('segment_end', 1);
            $table->jsonb('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['fiber_core_id', 'segment_end']);
            $table->index('company_id');
            $table->index('network_connection_point_id');
        });

        DB::statement('ALTER TABLE fiber_terminations ADD CONSTRAINT fiber_terminations_core_company_foreign FOREIGN KEY (fiber_core_id, company_id) REFERENCES fiber_cores (id, company_id) ON DELETE RESTRICT');
        DB::statement('ALTER TABLE fiber_terminations ADD CONSTRAINT fiber_terminations_ncp_company_foreign FOREIGN KEY (network_connection_point_id, company_id) REFERENCES network_connection_points (id, company_id) ON DELETE RESTRICT');
        DB::statement("ALTER TABLE fiber_terminations ADD CONSTRAINT fiber_terminations_segment_end_check CHECK (segment_end IN ('A', 'B'))");

        DB::unprepared('DROP TRIGGER IF EXISTS enforce_fiber_termination_endpoint_trigger ON fiber_terminations; DROP FUNCTION IF EXISTS enforce_fiber_termination_endpoint(); DROP TRIGGER IF EXISTS prevent_referenced_fiber_core_soft_delete_trigger ON fiber_cores; DROP FUNCTION IF EXISTS prevent_referenced_fiber_core_soft_delete(); DROP TRIGGER IF EXISTS prevent_terminated_fiber_segment_endpoint_update_trigger ON fiber_segments; DROP FUNCTION IF EXISTS prevent_terminated_fiber_segment_endpoint_update();');
        DB::unprepared(<<<'SQL'
            CREATE FUNCTION enforce_fiber_termination_endpoint() RETURNS trigger AS $$
            DECLARE endpoint_id bigint;
            BEGIN
                SELECT CASE NEW.segment_end WHEN 'A' THEN fs.endpoint_a_id ELSE fs.endpoint_b_id END
                INTO endpoint_id
                FROM fiber_cores fc
                JOIN fiber_segments fs ON fs.id = fc.fiber_segment_id
                WHERE fc.id = NEW.fiber_core_id;

                IF endpoint_id IS NULL OR NEW.network_connection_point_id <> endpoint_id THEN
                    RAISE EXCEPTION 'fiber termination NCP must match the selected fiber segment end'
                        USING ERRCODE = '23503';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER enforce_fiber_termination_endpoint_trigger
                BEFORE INSERT OR UPDATE OF fiber_core_id, network_connection_point_id, segment_end ON fiber_terminations
                FOR EACH ROW EXECUTE FUNCTION enforce_fiber_termination_endpoint();

            CREATE FUNCTION prevent_referenced_fiber_core_soft_delete() RETURNS trigger AS $$
            BEGIN
                IF NEW.deleted_at IS NOT NULL AND OLD.deleted_at IS NULL
                    AND EXISTS (SELECT 1 FROM fiber_terminations WHERE fiber_core_id = OLD.id AND deleted_at IS NULL) THEN
                    RAISE EXCEPTION 'fiber core % is referenced by a live fiber termination', OLD.id
                        USING ERRCODE = '23503';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER prevent_referenced_fiber_core_soft_delete_trigger
                BEFORE UPDATE OF deleted_at ON fiber_cores
                FOR EACH ROW EXECUTE FUNCTION prevent_referenced_fiber_core_soft_delete();

            CREATE FUNCTION prevent_terminated_fiber_segment_endpoint_update() RETURNS trigger AS $$
            BEGIN
                IF NEW.endpoint_a_id IS DISTINCT FROM OLD.endpoint_a_id
                    AND EXISTS (
                        SELECT 1 FROM fiber_terminations ft
                        JOIN fiber_cores fc ON fc.id = ft.fiber_core_id
                        WHERE fc.fiber_segment_id = OLD.id
                          AND ft.segment_end = 'A'
                          AND ft.deleted_at IS NULL
                    ) THEN
                    RAISE EXCEPTION 'fiber segment endpoint A has live fiber terminations'
                        USING ERRCODE = '23503';
                END IF;
                IF NEW.endpoint_b_id IS DISTINCT FROM OLD.endpoint_b_id
                    AND EXISTS (
                        SELECT 1 FROM fiber_terminations ft
                        JOIN fiber_cores fc ON fc.id = ft.fiber_core_id
                        WHERE fc.fiber_segment_id = OLD.id
                          AND ft.segment_end = 'B'
                          AND ft.deleted_at IS NULL
                    ) THEN
                    RAISE EXCEPTION 'fiber segment endpoint B has live fiber terminations'
                        USING ERRCODE = '23503';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER prevent_terminated_fiber_segment_endpoint_update_trigger
                BEFORE UPDATE OF endpoint_a_id, endpoint_b_id ON fiber_segments
                FOR EACH ROW EXECUTE FUNCTION prevent_terminated_fiber_segment_endpoint_update();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS enforce_fiber_termination_endpoint_trigger ON fiber_terminations; DROP FUNCTION IF EXISTS enforce_fiber_termination_endpoint(); DROP TRIGGER IF EXISTS prevent_referenced_fiber_core_soft_delete_trigger ON fiber_cores; DROP FUNCTION IF EXISTS prevent_referenced_fiber_core_soft_delete(); DROP TRIGGER IF EXISTS prevent_terminated_fiber_segment_endpoint_update_trigger ON fiber_segments; DROP FUNCTION IF EXISTS prevent_terminated_fiber_segment_endpoint_update();');
        Schema::dropIfExists('fiber_terminations');
        DB::statement('ALTER TABLE fiber_cores DROP CONSTRAINT fiber_cores_id_company_unique');
    }
};
