<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routing_l3_interfaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('routing_instance_id')->constrained()->restrictOnDelete();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->string('kind', 20);
            $table->foreignId('network_port_id')->nullable()->constrained('network_ports')->restrictOnDelete();
            $table->foreignId('vlan_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('parent_routing_l3_interface_id')->nullable()->constrained('routing_l3_interfaces')->restrictOnDelete();
            $table->jsonb('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
        DB::unprepared(<<<'SQL'
            ALTER TABLE routing_l3_interfaces ADD CONSTRAINT routing_l3_interfaces_kind_check CHECK (kind IN ('physical','svi','subinterface','loopback'));
            ALTER TABLE routing_l3_interfaces ADD CONSTRAINT routing_l3_interfaces_name_nonempty_check CHECK (length(btrim(name)) > 0);
            ALTER TABLE routing_l3_interfaces ADD CONSTRAINT routing_l3_interfaces_shape_check CHECK (
                (kind = 'physical' AND network_port_id IS NOT NULL AND vlan_id IS NULL AND parent_routing_l3_interface_id IS NULL) OR
                (kind = 'svi' AND network_port_id IS NULL AND vlan_id IS NOT NULL AND parent_routing_l3_interface_id IS NULL) OR
                (kind = 'subinterface' AND network_port_id IS NULL AND vlan_id IS NOT NULL AND parent_routing_l3_interface_id IS NOT NULL) OR
                (kind = 'loopback' AND network_port_id IS NULL AND vlan_id IS NULL AND parent_routing_l3_interface_id IS NULL)
            );
            CREATE UNIQUE INDEX routing_l3_interfaces_live_ri_name_unique ON routing_l3_interfaces (routing_instance_id, name) WHERE deleted_at IS NULL;
            CREATE UNIQUE INDEX routing_l3_interfaces_live_physical_port_unique ON routing_l3_interfaces (routing_instance_id, network_port_id) WHERE deleted_at IS NULL AND kind = 'physical';
            CREATE UNIQUE INDEX routing_l3_interfaces_live_svi_vlan_unique ON routing_l3_interfaces (routing_instance_id, vlan_id) WHERE deleted_at IS NULL AND kind = 'svi';
            CREATE UNIQUE INDEX routing_l3_interfaces_live_subinterface_parent_vlan_unique ON routing_l3_interfaces (parent_routing_l3_interface_id, vlan_id) WHERE deleted_at IS NULL AND kind = 'subinterface';
            CREATE INDEX routing_l3_interfaces_company_id_index ON routing_l3_interfaces (company_id);

            CREATE OR REPLACE FUNCTION ned007b_validate_routing_l3_interface() RETURNS trigger AS $$
            DECLARE ri record; port record; vlan record; parent record;
            BEGIN
                IF TG_OP = 'DELETE' THEN RAISE EXCEPTION 'routing L3 interface history cannot be deleted' USING ERRCODE = '23503'; END IF;
                IF TG_OP = 'UPDATE' THEN
                    IF (OLD.routing_instance_id, OLD.asset_id, OLD.company_id, OLD.name, OLD.kind, OLD.network_port_id, OLD.vlan_id, OLD.parent_routing_l3_interface_id)
                       IS DISTINCT FROM (NEW.routing_instance_id, NEW.asset_id, NEW.company_id, NEW.name, NEW.kind, NEW.network_port_id, NEW.vlan_id, NEW.parent_routing_l3_interface_id) THEN RAISE EXCEPTION 'routing L3 interface identity is immutable' USING ERRCODE = '23503'; END IF;
                    IF OLD.deleted_at IS NOT NULL AND NEW.deleted_at IS NULL THEN RAISE EXCEPTION 'routing L3 interface history cannot be restored' USING ERRCODE = '23503'; END IF;
                    IF NEW.deleted_at IS NOT NULL THEN
                        IF EXISTS (SELECT 1 FROM routing_l3_interfaces WHERE parent_routing_l3_interface_id = OLD.id AND deleted_at IS NULL) THEN RAISE EXCEPTION 'cannot retire physical L3 interface with live subinterfaces' USING ERRCODE = '23503'; END IF;
                        RETURN NEW;
                    END IF;
                END IF;
                PERFORM pg_advisory_xact_lock(NEW.routing_instance_id);
                IF NEW.network_port_id IS NOT NULL THEN PERFORM pg_advisory_xact_lock(NEW.network_port_id); END IF;
                IF NEW.vlan_id IS NOT NULL THEN PERFORM pg_advisory_xact_lock(NEW.vlan_id); END IF;
                IF NEW.parent_routing_l3_interface_id IS NOT NULL THEN PERFORM pg_advisory_xact_lock(NEW.parent_routing_l3_interface_id); END IF;
                SELECT * INTO ri FROM routing_instances WHERE id = NEW.routing_instance_id FOR KEY SHARE;
                IF NOT FOUND OR ri.deleted_at IS NOT NULL OR ri.asset_id IS DISTINCT FROM NEW.asset_id OR ri.company_id IS DISTINCT FROM NEW.company_id THEN RAISE EXCEPTION 'routing L3 interface requires a live same-asset company routing instance' USING ERRCODE = '23503'; END IF;
                IF NEW.kind = 'physical' THEN
                    SELECT * INTO port FROM network_ports WHERE id = NEW.network_port_id FOR KEY SHARE;
                    IF NOT FOUND OR port.deleted_at IS NOT NULL OR port.asset_id IS DISTINCT FROM NEW.asset_id OR port.company_id IS DISTINCT FROM NEW.company_id THEN RAISE EXCEPTION 'physical L3 interface requires a live same-asset company network port' USING ERRCODE = '23503'; END IF;
                ELSIF NEW.kind IN ('svi','subinterface') THEN
                    SELECT * INTO vlan FROM vlans WHERE id = NEW.vlan_id FOR KEY SHARE;
                    IF NOT FOUND OR vlan.deleted_at IS NOT NULL OR vlan.company_id IS DISTINCT FROM NEW.company_id THEN RAISE EXCEPTION 'VLAN-backed L3 interface requires a live same-company VLAN' USING ERRCODE = '23503'; END IF;
                    IF NEW.kind = 'subinterface' THEN
                        SELECT * INTO parent FROM routing_l3_interfaces WHERE id = NEW.parent_routing_l3_interface_id FOR KEY SHARE;
                        IF NOT FOUND OR parent.deleted_at IS NOT NULL OR parent.kind <> 'physical' OR parent.routing_instance_id IS DISTINCT FROM NEW.routing_instance_id OR parent.asset_id IS DISTINCT FROM NEW.asset_id OR parent.company_id IS DISTINCT FROM NEW.company_id THEN RAISE EXCEPTION 'subinterface requires a live same-routing-instance physical parent' USING ERRCODE = '23503'; END IF;
                    END IF;
                END IF;
                RETURN NEW;
            END; $$ LANGUAGE plpgsql;
            CREATE TRIGGER ned007b_validate_routing_l3_interface BEFORE INSERT OR UPDATE OR DELETE ON routing_l3_interfaces FOR EACH ROW EXECUTE FUNCTION ned007b_validate_routing_l3_interface();

            CREATE OR REPLACE FUNCTION ned007b_protect_l3_parents() RETURNS trigger AS $$
            DECLARE protected boolean; changed boolean;
            BEGIN
                IF TG_TABLE_NAME = 'routing_instances' THEN SELECT EXISTS (SELECT 1 FROM routing_l3_interfaces WHERE routing_instance_id = OLD.id) INTO protected;
                ELSIF TG_TABLE_NAME = 'network_ports' THEN SELECT EXISTS (SELECT 1 FROM routing_l3_interfaces WHERE network_port_id = OLD.id) INTO protected;
                ELSIF TG_TABLE_NAME = 'vlans' THEN SELECT EXISTS (SELECT 1 FROM routing_l3_interfaces WHERE vlan_id = OLD.id) INTO protected;
                ELSE SELECT EXISTS (SELECT 1 FROM routing_l3_interfaces WHERE asset_id = OLD.id) INTO protected; END IF;
                IF TG_OP = 'UPDATE' THEN
                    IF TG_TABLE_NAME = 'routing_instances' THEN changed := OLD.deleted_at IS NULL AND NEW.deleted_at IS NOT NULL;
                    ELSIF TG_TABLE_NAME = 'network_ports' THEN changed := (OLD.asset_id, OLD.company_id, OLD.port_key) IS DISTINCT FROM (NEW.asset_id, NEW.company_id, NEW.port_key) OR (OLD.deleted_at IS NULL AND NEW.deleted_at IS NOT NULL);
                    ELSIF TG_TABLE_NAME = 'vlans' THEN changed := OLD.deleted_at IS NULL AND NEW.deleted_at IS NOT NULL;
                    ELSE changed := (OLD.company_id, OLD.category) IS DISTINCT FROM (NEW.company_id, NEW.category) OR (OLD.deleted_at IS NULL AND NEW.deleted_at IS NOT NULL); END IF;
                END IF;
                IF protected AND (TG_OP = 'DELETE' OR changed) THEN RAISE EXCEPTION 'parent has routing L3 interface history' USING ERRCODE = '23503'; END IF;
                IF TG_OP = 'DELETE' THEN RETURN OLD; END IF; RETURN NEW;
            END; $$ LANGUAGE plpgsql;
            CREATE TRIGGER ned007b_protect_routing_instance_l3_history BEFORE UPDATE OR DELETE ON routing_instances FOR EACH ROW EXECUTE FUNCTION ned007b_protect_l3_parents();
            CREATE TRIGGER ned007b_protect_network_port_l3_history BEFORE UPDATE OR DELETE ON network_ports FOR EACH ROW EXECUTE FUNCTION ned007b_protect_l3_parents();
            CREATE TRIGGER ned007b_protect_vlan_l3_history BEFORE UPDATE OR DELETE ON vlans FOR EACH ROW EXECUTE FUNCTION ned007b_protect_l3_parents();
            CREATE TRIGGER ned007b_protect_asset_l3_history BEFORE UPDATE OR DELETE ON assets FOR EACH ROW EXECUTE FUNCTION ned007b_protect_l3_parents();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS ned007b_validate_routing_l3_interface ON routing_l3_interfaces; DROP TRIGGER IF EXISTS ned007b_protect_routing_instance_l3_history ON routing_instances; DROP TRIGGER IF EXISTS ned007b_protect_network_port_l3_history ON network_ports; DROP TRIGGER IF EXISTS ned007b_protect_vlan_l3_history ON vlans; DROP TRIGGER IF EXISTS ned007b_protect_asset_l3_history ON assets; DROP FUNCTION IF EXISTS ned007b_validate_routing_l3_interface(); DROP FUNCTION IF EXISTS ned007b_protect_l3_parents();');
        Schema::dropIfExists('routing_l3_interfaces');
    }
};
