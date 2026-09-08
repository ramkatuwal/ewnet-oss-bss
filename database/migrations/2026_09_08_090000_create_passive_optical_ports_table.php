<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Functions outlive migrate:fresh table drops, so remove stale definitions first.
        DB::unprepared('DROP FUNCTION IF EXISTS fim_validate_passive_optical_port(); DROP FUNCTION IF EXISTS fim_prevent_live_passive_optical_port_parent_soft_delete();');
        DB::statement('ALTER TABLE assets ADD CONSTRAINT assets_id_company_unique UNIQUE (id, company_id)');

        Schema::create('passive_optical_ports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->restrictOnDelete();
            $table->foreignId('network_connection_point_id')->constrained('network_connection_points')->restrictOnDelete();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('port_number');
            $table->string('connector_type')->nullable();
            $table->string('port_role', 20);
            $table->jsonb('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Identities remain reserved after soft deletion.
            $table->unique(['asset_id', 'port_number']);
            $table->index('company_id');
        });

        DB::statement('ALTER TABLE passive_optical_ports ADD CONSTRAINT passive_optical_ports_asset_company_foreign FOREIGN KEY (asset_id, company_id) REFERENCES assets (id, company_id) ON DELETE RESTRICT');
        DB::statement('ALTER TABLE passive_optical_ports ADD CONSTRAINT passive_optical_ports_ncp_company_foreign FOREIGN KEY (network_connection_point_id, company_id) REFERENCES network_connection_points (id, company_id) ON DELETE RESTRICT');
        DB::statement("ALTER TABLE passive_optical_ports ADD CONSTRAINT passive_optical_ports_role_check CHECK (port_role IN ('generic', 'splitter_input', 'splitter_output'))");

        DB::unprepared(<<<'SQL'
            CREATE FUNCTION fim_validate_passive_optical_port() RETURNS trigger AS $$
            DECLARE asset_deleted_at timestamp; point_deleted_at timestamp; asset_category varchar; asset_type varchar;
            BEGIN
                SELECT deleted_at, category, type INTO STRICT asset_deleted_at, asset_category, asset_type FROM assets WHERE id = NEW.asset_id;
                IF asset_deleted_at IS NOT NULL OR asset_category <> 'INFRASTRUCTURE' OR upper(asset_type) NOT IN ('ODF', 'FAT', 'FDT', 'FDH', 'CLOSURE', 'CABINET', 'SPLITTER') THEN
                    RAISE EXCEPTION 'passive optical port requires an active approved passive infrastructure asset' USING ERRCODE = '23503';
                END IF;
                IF NEW.port_role IN ('splitter_input', 'splitter_output') AND upper(asset_type) <> 'SPLITTER' THEN
                    RAISE EXCEPTION 'splitter port roles require a splitter asset' USING ERRCODE = '23503';
                END IF;
                SELECT deleted_at INTO STRICT point_deleted_at FROM network_connection_points WHERE id = NEW.network_connection_point_id;
                IF point_deleted_at IS NOT NULL THEN
                    RAISE EXCEPTION 'passive optical port connection point must be active' USING ERRCODE = '23503';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER fim_validate_passive_optical_port_trigger BEFORE INSERT OR UPDATE OF asset_id, network_connection_point_id, company_id ON passive_optical_ports FOR EACH ROW EXECUTE FUNCTION fim_validate_passive_optical_port();
            CREATE FUNCTION fim_prevent_live_passive_optical_port_parent_soft_delete() RETURNS trigger AS $$
            BEGIN
                IF NEW.deleted_at IS NOT NULL AND OLD.deleted_at IS NULL
                    AND ((TG_TABLE_NAME = 'assets' AND EXISTS (SELECT 1 FROM passive_optical_ports WHERE deleted_at IS NULL AND asset_id = OLD.id))
                    OR (TG_TABLE_NAME = 'network_connection_points' AND EXISTS (SELECT 1 FROM passive_optical_ports WHERE deleted_at IS NULL AND network_connection_point_id = OLD.id))) THEN
                    RAISE EXCEPTION 'parent has live passive optical ports' USING ERRCODE = '23503';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER fim_prevent_live_passive_optical_port_asset_soft_delete_trigger BEFORE UPDATE OF deleted_at ON assets FOR EACH ROW EXECUTE FUNCTION fim_prevent_live_passive_optical_port_parent_soft_delete();
            CREATE TRIGGER fim_prevent_live_passive_optical_port_ncp_soft_delete_trigger BEFORE UPDATE OF deleted_at ON network_connection_points FOR EACH ROW EXECUTE FUNCTION fim_prevent_live_passive_optical_port_parent_soft_delete();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS fim_prevent_live_passive_optical_port_asset_soft_delete_trigger ON assets; DROP TRIGGER IF EXISTS fim_prevent_live_passive_optical_port_ncp_soft_delete_trigger ON network_connection_points; DROP FUNCTION IF EXISTS fim_prevent_live_passive_optical_port_parent_soft_delete(); DROP TRIGGER IF EXISTS fim_validate_passive_optical_port_trigger ON passive_optical_ports; DROP FUNCTION IF EXISTS fim_validate_passive_optical_port();');
        Schema::dropIfExists('passive_optical_ports');
        DB::statement('ALTER TABLE assets DROP CONSTRAINT assets_id_company_unique');
    }
};
