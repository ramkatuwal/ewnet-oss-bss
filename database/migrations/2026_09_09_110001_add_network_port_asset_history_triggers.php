<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Drop existing triggers/functions if they exist (idempotent).
        DB::unprepared('DROP TRIGGER IF EXISTS fim_protect_network_port_asset_category_trigger ON assets;');
        DB::unprepared('DROP FUNCTION IF EXISTS fim_protect_network_port_asset_category();');
        DB::unprepared('DROP TRIGGER IF EXISTS fim_protect_network_port_asset_company_trigger ON assets;');
        DB::unprepared('DROP FUNCTION IF EXISTS fim_protect_network_port_asset_company();');
        DB::unprepared('DROP TRIGGER IF EXISTS fim_prevent_network_port_asset_soft_delete_trigger ON assets;');
        DB::unprepared('DROP FUNCTION IF EXISTS fim_prevent_network_port_asset_soft_delete();');

        DB::unprepared(<<<'SQL'
            -- Prevent Asset category change away from NETWORK if NetworkPort history exists.
            CREATE FUNCTION fim_protect_network_port_asset_category() RETURNS trigger AS $$
            BEGIN
                IF OLD.category IS DISTINCT FROM NEW.category
                    AND UPPER(OLD.category) = 'NETWORK'
                    AND EXISTS (SELECT 1 FROM network_ports WHERE asset_id = OLD.id) THEN
                    RAISE EXCEPTION 'asset has network port history; category cannot be changed away from NETWORK'
                        USING ERRCODE = '23503';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER fim_protect_network_port_asset_category_trigger
                BEFORE UPDATE OF category ON assets
                FOR EACH ROW EXECUTE FUNCTION fim_protect_network_port_asset_category();

            -- Prevent Asset company_id reassignment if NetworkPort history exists.
            CREATE FUNCTION fim_protect_network_port_asset_company() RETURNS trigger AS $$
            BEGIN
                IF OLD.company_id IS DISTINCT FROM NEW.company_id
                    AND EXISTS (SELECT 1 FROM network_ports WHERE asset_id = OLD.id) THEN
                    RAISE EXCEPTION 'asset has network port history; company_id cannot be reassigned'
                        USING ERRCODE = '23503';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER fim_protect_network_port_asset_company_trigger
                BEFORE UPDATE OF company_id ON assets
                FOR EACH ROW EXECUTE FUNCTION fim_protect_network_port_asset_company();

            -- Prevent Asset soft-delete if NetworkPort history exists (live or deleted).
            CREATE FUNCTION fim_prevent_network_port_asset_soft_delete() RETURNS trigger AS $$
            BEGIN
                IF NEW.deleted_at IS NOT NULL AND OLD.deleted_at IS NULL
                    AND EXISTS (SELECT 1 FROM network_ports WHERE asset_id = OLD.id) THEN
                    RAISE EXCEPTION 'asset has network port history; cannot delete'
                        USING ERRCODE = '23503';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER fim_prevent_network_port_asset_soft_delete_trigger
                BEFORE UPDATE OF deleted_at ON assets
                FOR EACH ROW EXECUTE FUNCTION fim_prevent_network_port_asset_soft_delete();
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS fim_protect_network_port_asset_category_trigger ON assets;');
        DB::unprepared('DROP FUNCTION IF EXISTS fim_protect_network_port_asset_category();');
        DB::unprepared('DROP TRIGGER IF EXISTS fim_protect_network_port_asset_company_trigger ON assets;');
        DB::unprepared('DROP FUNCTION IF EXISTS fim_protect_network_port_asset_company();');
        DB::unprepared('DROP TRIGGER IF EXISTS fim_prevent_network_port_asset_soft_delete_trigger ON assets;');
        DB::unprepared('DROP FUNCTION IF EXISTS fim_prevent_network_port_asset_soft_delete();');
    }
};
