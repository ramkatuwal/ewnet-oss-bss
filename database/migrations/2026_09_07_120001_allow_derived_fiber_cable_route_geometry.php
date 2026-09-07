<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Composite keys make a segment's denormalized company_id agree with
        // its cable and both physical endpoint boundaries at the database layer.
        DB::statement('ALTER TABLE fiber_cables ADD CONSTRAINT fiber_cables_id_company_unique UNIQUE (id, company_id)');
        DB::statement('ALTER TABLE network_connection_points ADD CONSTRAINT network_connection_points_id_company_unique UNIQUE (id, company_id)');
        DB::statement('ALTER TABLE fiber_segments ADD CONSTRAINT fiber_segments_cable_company_foreign FOREIGN KEY (fiber_cable_id, company_id) REFERENCES fiber_cables (id, company_id) ON DELETE RESTRICT');
        DB::statement('ALTER TABLE fiber_segments ADD CONSTRAINT fiber_segments_endpoint_a_company_foreign FOREIGN KEY (endpoint_a_id, company_id) REFERENCES network_connection_points (id, company_id) ON DELETE RESTRICT');
        DB::statement('ALTER TABLE fiber_segments ADD CONSTRAINT fiber_segments_endpoint_b_company_foreign FOREIGN KEY (endpoint_b_id, company_id) REFERENCES network_connection_points (id, company_id) ON DELETE RESTRICT');

        // A soft-deleted endpoint cannot remain a live topology boundary.
        // migrate:fresh drops tables but not standalone PostgreSQL functions.
        DB::unprepared('DROP TRIGGER IF EXISTS prevent_referenced_ncp_soft_delete_trigger ON network_connection_points; DROP FUNCTION IF EXISTS prevent_referenced_ncp_soft_delete();');
        DB::unprepared(<<<'SQL'
            CREATE FUNCTION prevent_referenced_ncp_soft_delete() RETURNS trigger AS $$
            BEGIN
                IF NEW.deleted_at IS NOT NULL AND OLD.deleted_at IS NULL
                    AND EXISTS (
                        SELECT 1 FROM fiber_segments
                        WHERE deleted_at IS NULL
                          AND (endpoint_a_id = OLD.id OR endpoint_b_id = OLD.id)
                    ) THEN
                    RAISE EXCEPTION 'network connection point % is referenced by a live fiber segment', OLD.id
                        USING ERRCODE = '23503';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER prevent_referenced_ncp_soft_delete_trigger
                BEFORE UPDATE OF deleted_at ON network_connection_points
                FOR EACH ROW EXECUTE FUNCTION prevent_referenced_ncp_soft_delete();
            SQL);

        // `cable` means a manually maintained cable route; `segments` means the
        // route is derived solely from live segment geometry and may be NULL.
        DB::statement("ALTER TABLE fiber_cables ADD COLUMN route_geometry_authority varchar(20) NOT NULL DEFAULT 'cable'");
        DB::statement('ALTER TABLE fiber_cables ALTER COLUMN route_geometry DROP NOT NULL');
    }

    public function down(): void
    {
        if (DB::table('fiber_cables')->whereNull('route_geometry')->exists()) {
            throw new RuntimeException('Cannot restore NOT NULL route_geometry while derived cable routes are NULL.');
        }

        DB::statement('ALTER TABLE fiber_cables ALTER COLUMN route_geometry SET NOT NULL');
        DB::statement('ALTER TABLE fiber_cables DROP COLUMN route_geometry_authority');
        DB::unprepared('DROP TRIGGER IF EXISTS prevent_referenced_ncp_soft_delete_trigger ON network_connection_points; DROP FUNCTION IF EXISTS prevent_referenced_ncp_soft_delete();');
        DB::statement('ALTER TABLE fiber_segments DROP CONSTRAINT fiber_segments_endpoint_b_company_foreign');
        DB::statement('ALTER TABLE fiber_segments DROP CONSTRAINT fiber_segments_endpoint_a_company_foreign');
        DB::statement('ALTER TABLE fiber_segments DROP CONSTRAINT fiber_segments_cable_company_foreign');
        DB::statement('ALTER TABLE network_connection_points DROP CONSTRAINT network_connection_points_id_company_unique');
        DB::statement('ALTER TABLE fiber_cables DROP CONSTRAINT fiber_cables_id_company_unique');
    }
};
