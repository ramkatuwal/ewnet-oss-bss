<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('static_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('routing_instance_id')->constrained()->restrictOnDelete();
            $table->foreignId('routing_l3_interface_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('route_type', 10);
            $table->jsonb('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE static_routes ADD COLUMN destination cidr NOT NULL;
            ALTER TABLE static_routes ADD COLUMN gateway inet NULL;
            ALTER TABLE static_routes ADD CONSTRAINT static_routes_type_check CHECK (route_type IN ('forward', 'discard', 'reject'));
            ALTER TABLE static_routes ADD CONSTRAINT static_routes_destination_canonical_check CHECK (destination = network(destination));
            ALTER TABLE static_routes ADD CONSTRAINT static_routes_gateway_host_check CHECK (gateway IS NULL OR masklen(gateway) = CASE family(gateway) WHEN 4 THEN 32 WHEN 6 THEN 128 END);
            ALTER TABLE static_routes ADD CONSTRAINT static_routes_gateway_family_check CHECK (gateway IS NULL OR family(destination) = family(gateway));
            CREATE UNIQUE INDEX static_routes_live_ri_destination_unique ON static_routes (routing_instance_id, destination) WHERE deleted_at IS NULL;
            CREATE INDEX static_routes_company_id_index ON static_routes (company_id);

            CREATE OR REPLACE FUNCTION ned007d_validate_static_route() RETURNS trigger AS $$
            DECLARE ri record; interface record;
            BEGIN
                IF TG_OP = 'DELETE' THEN RAISE EXCEPTION 'static route history cannot be deleted' USING ERRCODE = '23503'; END IF;
                IF TG_OP = 'UPDATE' THEN
                    IF (OLD.routing_instance_id, OLD.routing_l3_interface_id, OLD.asset_id, OLD.company_id, OLD.destination, OLD.gateway, OLD.route_type)
                       IS DISTINCT FROM (NEW.routing_instance_id, NEW.routing_l3_interface_id, NEW.asset_id, NEW.company_id, NEW.destination, NEW.gateway, NEW.route_type) THEN RAISE EXCEPTION 'static route identity is immutable' USING ERRCODE = '23503'; END IF;
                    IF OLD.deleted_at IS NOT NULL AND NEW.deleted_at IS NULL THEN RAISE EXCEPTION 'static route history cannot be restored' USING ERRCODE = '23503'; END IF;
                    IF NEW.deleted_at IS NOT NULL THEN RETURN NEW; END IF;
                END IF;
                PERFORM pg_advisory_xact_lock(NEW.routing_instance_id);
                IF NEW.routing_l3_interface_id IS NOT NULL THEN PERFORM pg_advisory_xact_lock(NEW.routing_l3_interface_id); END IF;
                SELECT * INTO ri FROM routing_instances WHERE id = NEW.routing_instance_id FOR KEY SHARE;
                IF NOT FOUND OR ri.deleted_at IS NOT NULL OR (ri.asset_id, ri.company_id) IS DISTINCT FROM (NEW.asset_id, NEW.company_id) THEN RAISE EXCEPTION 'static route requires a live routing instance with matching ownership' USING ERRCODE = '23503'; END IF;
                IF NEW.routing_l3_interface_id IS NOT NULL THEN
                    SELECT * INTO interface FROM routing_l3_interfaces WHERE id = NEW.routing_l3_interface_id FOR KEY SHARE;
                    IF NOT FOUND OR interface.deleted_at IS NOT NULL OR (interface.routing_instance_id, interface.asset_id, interface.company_id) IS DISTINCT FROM (NEW.routing_instance_id, NEW.asset_id, NEW.company_id) THEN RAISE EXCEPTION 'static route interface requires a live same-routing-instance parent' USING ERRCODE = '23503'; END IF;
                END IF;
                RETURN NEW;
            END; $$ LANGUAGE plpgsql;
            CREATE TRIGGER ned007d_validate_static_route BEFORE INSERT OR UPDATE OR DELETE ON static_routes FOR EACH ROW EXECUTE FUNCTION ned007d_validate_static_route();

            CREATE OR REPLACE FUNCTION ned007d_protect_static_route_parents() RETURNS trigger AS $$
            DECLARE protected boolean; changed boolean;
            BEGIN
                IF TG_TABLE_NAME = 'routing_instances' THEN
                    SELECT EXISTS (SELECT 1 FROM static_routes WHERE routing_instance_id = OLD.id) INTO protected;
                ELSE
                    SELECT EXISTS (SELECT 1 FROM static_routes WHERE routing_l3_interface_id = OLD.id) INTO protected;
                END IF;
                IF TG_OP = 'UPDATE' THEN
                    changed := OLD.deleted_at IS NULL AND NEW.deleted_at IS NOT NULL;
                END IF;
                IF protected AND (TG_OP = 'DELETE' OR changed) THEN RAISE EXCEPTION 'parent has static route history' USING ERRCODE = '23503'; END IF;
                IF TG_OP = 'DELETE' THEN RETURN OLD; END IF;
                RETURN NEW;
            END; $$ LANGUAGE plpgsql;
            CREATE TRIGGER ned007d_protect_routing_instance_static_route_history BEFORE UPDATE OR DELETE ON routing_instances FOR EACH ROW EXECUTE FUNCTION ned007d_protect_static_route_parents();
            CREATE TRIGGER ned007d_protect_l3_interface_static_route_history BEFORE UPDATE OR DELETE ON routing_l3_interfaces FOR EACH ROW EXECUTE FUNCTION ned007d_protect_static_route_parents();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS ned007d_validate_static_route ON static_routes; DROP TRIGGER IF EXISTS ned007d_protect_routing_instance_static_route_history ON routing_instances; DROP TRIGGER IF EXISTS ned007d_protect_l3_interface_static_route_history ON routing_l3_interfaces; DROP FUNCTION IF EXISTS ned007d_validate_static_route(); DROP FUNCTION IF EXISTS ned007d_protect_static_route_parents();');
        Schema::dropIfExists('static_routes');
    }
};
