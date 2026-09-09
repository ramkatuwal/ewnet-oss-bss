<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Keep writers out between the read-only conflict preflight and enforcement.
        DB::statement('LOCK TABLE physical_connections, fiber_termination_port_attachments IN SHARE ROW EXCLUSIVE MODE');
        $conflict = DB::selectOne(<<<'SQL'
            SELECT termination_id FROM (
                SELECT termination_a_id AS termination_id FROM physical_connections WHERE deleted_at IS NULL
                UNION ALL SELECT termination_b_id FROM physical_connections WHERE deleted_at IS NULL
                UNION ALL SELECT fiber_termination_id FROM fiber_termination_port_attachments WHERE deleted_at IS NULL
            ) edges GROUP BY termination_id HAVING count(*) > 1 LIMIT 1
            SQL);
        if ($conflict !== null) {
            throw new RuntimeException('NED-004 preflight: existing live termination conflicts; no data repaired.');
        }

        Schema::create('network_port_fiber_termination_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('network_port_id')->constrained('network_ports')->restrictOnDelete();
            $table->foreignId('fiber_termination_id')->constrained('fiber_terminations')->restrictOnDelete();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->jsonb('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index('company_id', 'npfta_company_index');
            $table->index('network_port_id', 'npfta_port_history_index');
            $table->index('fiber_termination_id', 'npfta_termination_history_index');
        });
        DB::statement('CREATE UNIQUE INDEX npfta_live_port_unique ON network_port_fiber_termination_attachments (network_port_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX npfta_live_termination_unique ON network_port_fiber_termination_attachments (fiber_termination_id) WHERE deleted_at IS NULL');

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION ned004_external_exclusivity() RETURNS trigger AS $$
            DECLARE ids bigint[]; endpoint bigint;
            BEGIN
                IF TG_TABLE_NAME = 'physical_connections' THEN
                    ids := ARRAY[NEW.termination_a_id, NEW.termination_b_id];
                ELSE
                    ids := ARRAY[NEW.fiber_termination_id];
                END IF;
                -- Runs before every other endpoint validator, including metadata updates.
                PERFORM id FROM fiber_terminations WHERE id = ANY(ids) ORDER BY id FOR UPDATE;
                IF NEW.deleted_at IS NOT NULL THEN RETURN NEW; END IF;
                FOREACH endpoint IN ARRAY ids LOOP
                    IF EXISTS (SELECT 1 FROM physical_connections
                        WHERE deleted_at IS NULL AND (termination_a_id = endpoint OR termination_b_id = endpoint)
                        AND NOT (TG_TABLE_NAME = 'physical_connections' AND id = NEW.id))
                    OR EXISTS (SELECT 1 FROM fiber_termination_port_attachments
                        WHERE deleted_at IS NULL AND fiber_termination_id = endpoint
                        AND NOT (TG_TABLE_NAME = 'fiber_termination_port_attachments' AND id = NEW.id))
                    OR EXISTS (SELECT 1 FROM network_port_fiber_termination_attachments
                        WHERE deleted_at IS NULL AND fiber_termination_id = endpoint
                        AND NOT (TG_TABLE_NAME = 'network_port_fiber_termination_attachments' AND id = NEW.id)) THEN
                        RAISE EXCEPTION 'fiber termination already has a live external connection' USING ERRCODE = '23505';
                    END IF;
                END LOOP;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER aaa_ned004_external_exclusivity BEFORE INSERT OR UPDATE ON physical_connections
                FOR EACH ROW EXECUTE FUNCTION ned004_external_exclusivity();
            CREATE TRIGGER aaa_ned004_external_exclusivity BEFORE INSERT OR UPDATE ON fiber_termination_port_attachments
                FOR EACH ROW EXECUTE FUNCTION ned004_external_exclusivity();
            CREATE TRIGGER aaa_ned004_external_exclusivity BEFORE INSERT OR UPDATE ON network_port_fiber_termination_attachments
                FOR EACH ROW EXECUTE FUNCTION ned004_external_exclusivity();

            CREATE OR REPLACE FUNCTION ned004_validate_attachment() RETURNS trigger AS $$
            DECLARE term record; port record; parent record; point record;
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    RAISE EXCEPTION 'network fiber attachment history cannot be deleted' USING ERRCODE = '23503';
                END IF;
                IF TG_OP = 'UPDATE' THEN
                    IF (OLD.network_port_id, OLD.fiber_termination_id, OLD.company_id)
                        IS DISTINCT FROM (NEW.network_port_id, NEW.fiber_termination_id, NEW.company_id) THEN
                        RAISE EXCEPTION 'network fiber attachment identity is immutable' USING ERRCODE = '23503';
                    END IF;
                    IF OLD.deleted_at IS NOT NULL AND NEW.deleted_at IS NULL THEN
                        RAISE EXCEPTION 'reconnect requires a new network fiber attachment' USING ERRCODE = '23503';
                    END IF;
                    IF NEW.deleted_at IS NOT NULL THEN RETURN NEW; END IF;
                END IF;
                SELECT * INTO STRICT term FROM fiber_terminations WHERE id = NEW.fiber_termination_id FOR UPDATE;
                -- Match NCP UPDATE -> network_port_id FK locking, never port -> NCP.
                SELECT * INTO STRICT point FROM network_connection_points WHERE id = term.network_connection_point_id FOR UPDATE;
                SELECT * INTO STRICT port FROM network_ports WHERE id = NEW.network_port_id FOR UPDATE;
                SELECT * INTO STRICT parent FROM assets WHERE id = port.asset_id FOR UPDATE;
                IF term.deleted_at IS NOT NULL OR port.deleted_at IS NOT NULL OR parent.deleted_at IS NOT NULL
                    OR UPPER(parent.category) IS DISTINCT FROM 'NETWORK'
                    OR term.company_id IS DISTINCT FROM NEW.company_id
                    OR port.company_id IS DISTINCT FROM NEW.company_id
                    OR parent.company_id IS DISTINCT FROM NEW.company_id
                    OR (point.network_port_id IS NOT NULL AND point.network_port_id <> port.id) THEN
                    RAISE EXCEPTION 'network fiber attachment requires live same-company endpoints and NETWORK parent without an explicit connection point contradiction' USING ERRCODE = '23503';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER ned004_validate_attachment BEFORE INSERT OR UPDATE OR DELETE ON network_port_fiber_termination_attachments
                FOR EACH ROW EXECUTE FUNCTION ned004_validate_attachment();

            CREATE OR REPLACE FUNCTION ned004_protect_endpoint_history() RETURNS trigger AS $$
            DECLARE protected boolean; changed boolean;
            BEGIN
                IF TG_TABLE_NAME = 'network_ports' THEN
                    SELECT EXISTS (SELECT 1 FROM network_port_fiber_termination_attachments WHERE network_port_id = OLD.id) INTO protected;
                    IF TG_OP = 'UPDATE' THEN
                        changed := (OLD.id, OLD.asset_id, OLD.company_id, OLD.port_key) IS DISTINCT FROM (NEW.id, NEW.asset_id, NEW.company_id, NEW.port_key)
                            OR (OLD.deleted_at IS NULL AND NEW.deleted_at IS NOT NULL);
                    END IF;
                ELSE
                    SELECT EXISTS (SELECT 1 FROM network_port_fiber_termination_attachments WHERE fiber_termination_id = OLD.id) INTO protected;
                    IF TG_OP = 'UPDATE' THEN
                        changed := (OLD.id, OLD.fiber_core_id, OLD.company_id, OLD.segment_end, OLD.network_connection_point_id)
                            IS DISTINCT FROM (NEW.id, NEW.fiber_core_id, NEW.company_id, NEW.segment_end, NEW.network_connection_point_id)
                            OR (OLD.deleted_at IS NULL AND NEW.deleted_at IS NOT NULL);
                    END IF;
                END IF;
                IF protected AND (TG_OP = 'DELETE' OR changed) THEN
                    RAISE EXCEPTION 'endpoint has network fiber attachment history' USING ERRCODE = '23503';
                END IF;
                IF TG_OP = 'DELETE' THEN RETURN OLD; END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER ned004_protect_endpoint_history BEFORE UPDATE OR DELETE ON network_ports
                FOR EACH ROW EXECUTE FUNCTION ned004_protect_endpoint_history();
            CREATE TRIGGER ned004_protect_endpoint_history BEFORE UPDATE OR DELETE ON fiber_terminations
                FOR EACH ROW EXECUTE FUNCTION ned004_protect_endpoint_history();

            CREATE OR REPLACE FUNCTION ned004_protect_ncp_attachment() RETURNS trigger AS $$
            BEGIN
                IF NEW.network_port_id IS DISTINCT FROM OLD.network_port_id AND NEW.network_port_id IS NOT NULL
                    AND EXISTS (SELECT 1 FROM network_port_fiber_termination_attachments a
                        JOIN fiber_terminations t ON t.id = a.fiber_termination_id
                        WHERE t.network_connection_point_id = OLD.id AND a.network_port_id <> NEW.network_port_id) THEN
                    RAISE EXCEPTION 'connection point contradicts network fiber attachment history' USING ERRCODE = '23503';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER ned004_protect_ncp_attachment BEFORE UPDATE ON network_connection_points
                FOR EACH ROW EXECUTE FUNCTION ned004_protect_ncp_attachment();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS aaa_ned004_external_exclusivity ON physical_connections;
            DROP TRIGGER IF EXISTS aaa_ned004_external_exclusivity ON fiber_termination_port_attachments;
            DROP TRIGGER IF EXISTS aaa_ned004_external_exclusivity ON network_port_fiber_termination_attachments;
            DROP TRIGGER IF EXISTS ned004_validate_attachment ON network_port_fiber_termination_attachments;
            DROP TRIGGER IF EXISTS ned004_protect_endpoint_history ON network_ports;
            DROP TRIGGER IF EXISTS ned004_protect_endpoint_history ON fiber_terminations;
            DROP TRIGGER IF EXISTS ned004_protect_ncp_attachment ON network_connection_points;
            DROP FUNCTION IF EXISTS ned004_external_exclusivity();
            DROP FUNCTION IF EXISTS ned004_validate_attachment();
            DROP FUNCTION IF EXISTS ned004_protect_endpoint_history();
            DROP FUNCTION IF EXISTS ned004_protect_ncp_attachment();
            SQL);
        Schema::dropIfExists('network_port_fiber_termination_attachments');
    }
};
