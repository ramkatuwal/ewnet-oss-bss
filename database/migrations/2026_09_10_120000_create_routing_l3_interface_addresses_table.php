<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routing_l3_interface_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('routing_l3_interface_id')->constrained()->restrictOnDelete();
            $table->foreignId('routing_instance_id')->constrained()->restrictOnDelete();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('address_role', 10);
            $table->unsignedSmallInteger('prefix_length');
            $table->jsonb('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE routing_l3_interface_addresses ADD COLUMN address inet NOT NULL;
            ALTER TABLE routing_l3_interface_addresses ADD CONSTRAINT routing_l3_interface_addresses_role_check CHECK (address_role IN ('primary', 'secondary'));
            ALTER TABLE routing_l3_interface_addresses ADD CONSTRAINT routing_l3_interface_addresses_prefix_check CHECK (prefix_length = masklen(address));
            CREATE INDEX routing_l3_interface_addresses_company_id_index ON routing_l3_interface_addresses (company_id);
            CREATE UNIQUE INDEX routing_l3_interface_addresses_live_primary_family_unique ON routing_l3_interface_addresses (routing_l3_interface_id, family(address)) WHERE deleted_at IS NULL AND address_role = 'primary';
            CREATE UNIQUE INDEX routing_l3_interface_addresses_live_ri_host_unique ON routing_l3_interface_addresses (routing_instance_id, family(address), host(address)) WHERE deleted_at IS NULL;

            CREATE OR REPLACE FUNCTION ned007c_validate_routing_l3_interface_address() RETURNS trigger AS $$
            DECLARE parent record;
            BEGIN
                IF TG_OP = 'DELETE' THEN RAISE EXCEPTION 'routing L3 interface address history cannot be deleted' USING ERRCODE = '23503'; END IF;
                IF TG_OP = 'UPDATE' THEN
                    IF (OLD.routing_l3_interface_id, OLD.routing_instance_id, OLD.asset_id, OLD.company_id, OLD.address, OLD.prefix_length, OLD.address_role)
                       IS DISTINCT FROM (NEW.routing_l3_interface_id, NEW.routing_instance_id, NEW.asset_id, NEW.company_id, NEW.address, NEW.prefix_length, NEW.address_role) THEN RAISE EXCEPTION 'routing L3 interface address identity is immutable' USING ERRCODE = '23503'; END IF;
                    IF OLD.deleted_at IS NOT NULL AND NEW.deleted_at IS NULL THEN RAISE EXCEPTION 'routing L3 interface address history cannot be restored' USING ERRCODE = '23503'; END IF;
                    IF NEW.deleted_at IS NOT NULL THEN RETURN NEW; END IF;
                END IF;
                PERFORM pg_advisory_xact_lock(NEW.routing_instance_id);
                PERFORM pg_advisory_xact_lock(NEW.routing_l3_interface_id);
                SELECT * INTO parent FROM routing_l3_interfaces WHERE id = NEW.routing_l3_interface_id FOR KEY SHARE;
                IF NOT FOUND OR parent.deleted_at IS NOT NULL
                   OR (parent.routing_instance_id, parent.asset_id, parent.company_id) IS DISTINCT FROM (NEW.routing_instance_id, NEW.asset_id, NEW.company_id) THEN
                    RAISE EXCEPTION 'routing L3 interface address requires a live parent with matching ownership' USING ERRCODE = '23503';
                END IF;
                RETURN NEW;
            END; $$ LANGUAGE plpgsql;
            CREATE TRIGGER ned007c_validate_routing_l3_interface_address BEFORE INSERT OR UPDATE OR DELETE ON routing_l3_interface_addresses FOR EACH ROW EXECUTE FUNCTION ned007c_validate_routing_l3_interface_address();

            CREATE OR REPLACE FUNCTION ned007b_validate_routing_l3_interface() RETURNS trigger AS $$
            DECLARE ri record; port record; vlan record; parent record;
            BEGIN
                IF TG_OP = 'DELETE' THEN RAISE EXCEPTION 'routing L3 interface history cannot be deleted' USING ERRCODE = '23503'; END IF;
                IF TG_OP = 'UPDATE' THEN
                    IF (OLD.routing_instance_id, OLD.asset_id, OLD.company_id, OLD.name, OLD.kind, OLD.network_port_id, OLD.vlan_id, OLD.parent_routing_l3_interface_id)
                       IS DISTINCT FROM (NEW.routing_instance_id, NEW.asset_id, NEW.company_id, NEW.name, NEW.kind, NEW.network_port_id, NEW.vlan_id, NEW.parent_routing_l3_interface_id) THEN RAISE EXCEPTION 'routing L3 interface identity is immutable' USING ERRCODE = '23503'; END IF;
                    IF OLD.deleted_at IS NOT NULL AND NEW.deleted_at IS NULL THEN RAISE EXCEPTION 'routing L3 interface history cannot be restored' USING ERRCODE = '23503'; END IF;
                    IF NEW.deleted_at IS NOT NULL THEN
                        IF EXISTS (SELECT 1 FROM routing_l3_interfaces WHERE parent_routing_l3_interface_id = OLD.id AND deleted_at IS NULL) THEN RAISE EXCEPTION 'cannot retire routing L3 interface with live dependents' USING ERRCODE = '23503'; END IF;
                        IF to_regclass('routing_l3_interface_addresses') IS NOT NULL AND EXISTS (SELECT 1 FROM routing_l3_interface_addresses WHERE routing_l3_interface_id = OLD.id AND deleted_at IS NULL) THEN RAISE EXCEPTION 'cannot retire routing L3 interface with live dependents' USING ERRCODE = '23503'; END IF;
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
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS ned007c_validate_routing_l3_interface_address ON routing_l3_interface_addresses; DROP FUNCTION IF EXISTS ned007c_validate_routing_l3_interface_address();');
        Schema::dropIfExists('routing_l3_interface_addresses');
    }
};
