<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // PostgreSQL functions survive migrate:fresh after their tables are dropped.
        DB::unprepared('DROP FUNCTION IF EXISTS fim_validate_fiber_termination_port_attachment(); DROP FUNCTION IF EXISTS fim_prevent_historical_fiber_termination_attachment_soft_delete(); DROP FUNCTION IF EXISTS fim_prevent_historical_passive_optical_port_attachment_soft_delete();');
        DB::statement('ALTER TABLE passive_optical_ports ADD CONSTRAINT passive_optical_ports_id_company_unique UNIQUE (id, company_id)');

        Schema::create('fiber_termination_port_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiber_termination_id')->constrained('fiber_terminations')->restrictOnDelete();
            $table->foreignId('passive_optical_port_id')->constrained('passive_optical_ports')->restrictOnDelete();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('company_id');
        });

        DB::statement('ALTER TABLE fiber_termination_port_attachments ADD CONSTRAINT fiber_termination_port_attachments_termination_company_foreign FOREIGN KEY (fiber_termination_id, company_id) REFERENCES fiber_terminations (id, company_id) ON DELETE RESTRICT');
        DB::statement('ALTER TABLE fiber_termination_port_attachments ADD CONSTRAINT fiber_termination_port_attachments_port_company_foreign FOREIGN KEY (passive_optical_port_id, company_id) REFERENCES passive_optical_ports (id, company_id) ON DELETE RESTRICT');
        DB::statement('CREATE UNIQUE INDEX fiber_termination_port_attachments_live_termination_unique ON fiber_termination_port_attachments (fiber_termination_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX fiber_termination_port_attachments_live_port_unique ON fiber_termination_port_attachments (passive_optical_port_id) WHERE deleted_at IS NULL');

        // These triggers are additive: FIM-003e physical-connection protections remain intact.
        DB::unprepared(<<<'SQL'
            CREATE FUNCTION fim_validate_fiber_termination_port_attachment() RETURNS trigger AS $$
            DECLARE termination record; port record;
            BEGIN
                IF NEW.deleted_at IS NOT NULL THEN
                    RETURN NEW;
                END IF;

                SELECT company_id, network_connection_point_id, deleted_at INTO STRICT termination
                FROM fiber_terminations WHERE id = NEW.fiber_termination_id;
                SELECT company_id, network_connection_point_id, deleted_at INTO STRICT port
                FROM passive_optical_ports WHERE id = NEW.passive_optical_port_id;

                IF termination.deleted_at IS NOT NULL
                    OR port.deleted_at IS NOT NULL
                    OR termination.company_id <> NEW.company_id
                    OR port.company_id <> NEW.company_id
                    OR termination.network_connection_point_id <> port.network_connection_point_id THEN
                    RAISE EXCEPTION 'fiber termination attachment requires active same-company endpoints at the same connection point' USING ERRCODE = '23503';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER fim_validate_fiber_termination_port_attachment_trigger
                BEFORE INSERT OR UPDATE OF fiber_termination_id, passive_optical_port_id, company_id, deleted_at
                ON fiber_termination_port_attachments
                FOR EACH ROW EXECUTE FUNCTION fim_validate_fiber_termination_port_attachment();

            CREATE FUNCTION fim_prevent_historical_fiber_termination_attachment_soft_delete() RETURNS trigger AS $$
            BEGIN
                IF NEW.deleted_at IS NOT NULL AND OLD.deleted_at IS NULL
                    AND EXISTS (SELECT 1 FROM fiber_termination_port_attachments WHERE fiber_termination_id = OLD.id) THEN
                    RAISE EXCEPTION 'fiber termination has port attachment history' USING ERRCODE = '23503';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER fim_prevent_historical_fiber_termination_attachment_soft_delete_trigger
                BEFORE UPDATE OF deleted_at ON fiber_terminations
                FOR EACH ROW EXECUTE FUNCTION fim_prevent_historical_fiber_termination_attachment_soft_delete();

            CREATE FUNCTION fim_prevent_historical_passive_optical_port_attachment_soft_delete() RETURNS trigger AS $$
            BEGIN
                IF NEW.deleted_at IS NOT NULL AND OLD.deleted_at IS NULL
                    AND EXISTS (SELECT 1 FROM fiber_termination_port_attachments WHERE passive_optical_port_id = OLD.id) THEN
                    RAISE EXCEPTION 'passive optical port has fiber termination attachment history' USING ERRCODE = '23503';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER fim_prevent_historical_passive_optical_port_attachment_soft_delete_trigger
                BEFORE UPDATE OF deleted_at ON passive_optical_ports
                FOR EACH ROW EXECUTE FUNCTION fim_prevent_historical_passive_optical_port_attachment_soft_delete();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS fim_prevent_historical_fiber_termination_attachment_soft_delete_trigger ON fiber_terminations; DROP FUNCTION IF EXISTS fim_prevent_historical_fiber_termination_attachment_soft_delete(); DROP TRIGGER IF EXISTS fim_prevent_historical_passive_optical_port_attachment_soft_delete_trigger ON passive_optical_ports; DROP FUNCTION IF EXISTS fim_prevent_historical_passive_optical_port_attachment_soft_delete(); DROP TRIGGER IF EXISTS fim_validate_fiber_termination_port_attachment_trigger ON fiber_termination_port_attachments; DROP FUNCTION IF EXISTS fim_validate_fiber_termination_port_attachment();');
        Schema::dropIfExists('fiber_termination_port_attachments');
        DB::statement('ALTER TABLE passive_optical_ports DROP CONSTRAINT passive_optical_ports_id_company_unique');
    }
};
