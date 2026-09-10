<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['sites', 'network_connection_points'] as $table) {
            $sridZero = (int) DB::scalar("SELECT count(*) FROM {$table} WHERE geometry IS NOT NULL AND ST_SRID(geometry) = 0");
            if ($sridZero > 0) {
                throw new RuntimeException("INFRA-002 cannot assign a CRS to {$sridZero} SRID-0 {$table}.geometry record(s).");
            }
            $invalid = (int) DB::scalar("SELECT count(*) FROM {$table} WHERE geometry IS NOT NULL AND (GeometryType(geometry) <> 'POINT' OR ST_SRID(geometry) <> 4326)");
            if ($invalid > 0) {
                throw new RuntimeException("INFRA-002 requires existing {$table}.geometry values to be POINT SRID 4326.");
            }
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE sites ALTER COLUMN geometry TYPE geometry(Point, 4326) USING geometry::geometry(Point, 4326);
            ALTER TABLE network_connection_points ALTER COLUMN geometry TYPE geometry(Point, 4326) USING geometry::geometry(Point, 4326);

            UPDATE sites SET geometry = ST_SetSRID(ST_MakePoint(longitude, latitude), 4326)
            WHERE geometry IS NULL AND latitude IS NOT NULL AND longitude IS NOT NULL;

            CREATE OR REPLACE FUNCTION infra002_sync_site_location() RETURNS trigger AS $$
            BEGIN
                IF NEW.geometry IS NOT NULL THEN
                    NEW.latitude := ST_Y(NEW.geometry);
                    NEW.longitude := ST_X(NEW.geometry);
                ELSIF NEW.latitude IS NOT NULL AND NEW.longitude IS NOT NULL THEN
                    NEW.geometry := ST_SetSRID(ST_MakePoint(NEW.longitude, NEW.latitude), 4326);
                ELSIF NEW.latitude IS NULL AND NEW.longitude IS NULL THEN
                    NEW.geometry := NULL;
                ELSE
                    RAISE EXCEPTION 'site latitude and longitude must be supplied together' USING ERRCODE = '23514';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER infra002_sync_site_location_trigger BEFORE INSERT OR UPDATE OF geometry, latitude, longitude ON sites
                FOR EACH ROW EXECUTE FUNCTION infra002_sync_site_location();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS infra002_sync_site_location_trigger ON sites;
            DROP FUNCTION IF EXISTS infra002_sync_site_location();
            ALTER TABLE sites ALTER COLUMN geometry TYPE geometry USING geometry::geometry;
            ALTER TABLE network_connection_points ALTER COLUMN geometry TYPE geometry USING geometry::geometry;
            SQL);
    }
};
