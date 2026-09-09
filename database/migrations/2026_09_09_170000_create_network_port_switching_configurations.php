<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('network_port_switching_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('network_port_id')->constrained('network_ports')->restrictOnDelete();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('mode', 20);
            $table->jsonb('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('network_port_vlan_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('network_port_switching_config_id')->constrained('network_port_switching_configs')->restrictOnDelete();
            $table->foreignId('vlan_id')->constrained('vlans')->restrictOnDelete();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('tagging', 20);
            $table->jsonb('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement("ALTER TABLE network_port_switching_configs ADD CONSTRAINT network_port_switching_configs_mode_check CHECK (mode IN ('access', 'trunk'))");
        DB::statement("ALTER TABLE network_port_vlan_memberships ADD CONSTRAINT network_port_vlan_memberships_tagging_check CHECK (tagging IN ('tagged', 'untagged'))");
        DB::statement('CREATE UNIQUE INDEX network_port_switching_configs_live_port_unique ON network_port_switching_configs (network_port_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX network_port_vlan_memberships_live_config_vlan_unique ON network_port_vlan_memberships (network_port_switching_config_id, vlan_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX network_port_switching_configs_company_id_index ON network_port_switching_configs (company_id)');
        DB::statement('CREATE INDEX network_port_vlan_memberships_company_id_index ON network_port_vlan_memberships (company_id)');
        DB::statement('CREATE INDEX network_port_vlan_memberships_vlan_id_index ON network_port_vlan_memberships (vlan_id)');

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION ned006b_validate_switching_config() RETURNS trigger AS $$
            DECLARE port record; asset record;
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    RAISE EXCEPTION 'switching configuration history cannot be deleted' USING ERRCODE = '23503';
                END IF;
                IF TG_OP = 'UPDATE' THEN
                    IF (OLD.network_port_id, OLD.company_id, OLD.mode) IS DISTINCT FROM (NEW.network_port_id, NEW.company_id, NEW.mode) THEN
                        RAISE EXCEPTION 'switching configuration identity is immutable' USING ERRCODE = '23503';
                    END IF;
                    IF OLD.deleted_at IS NOT NULL AND NEW.deleted_at IS NULL THEN
                        RAISE EXCEPTION 'switching configuration history cannot be restored' USING ERRCODE = '23503';
                    END IF;
                    IF NEW.deleted_at IS NOT NULL THEN RETURN NEW; END IF;
                END IF;
                SELECT * INTO port FROM network_ports WHERE id = NEW.network_port_id;
                IF NOT FOUND THEN
                    RAISE EXCEPTION 'switching configuration requires a valid network port' USING ERRCODE = '23503';
                END IF;
                SELECT * INTO asset FROM assets WHERE id = port.asset_id;
                IF NOT FOUND OR port.deleted_at IS NOT NULL OR asset.deleted_at IS NOT NULL
                    OR UPPER(asset.category) IS DISTINCT FROM 'NETWORK'
                    OR port.company_id IS DISTINCT FROM NEW.company_id
                    OR asset.company_id IS DISTINCT FROM NEW.company_id THEN
                    RAISE EXCEPTION 'switching configuration requires a live same-company network port on a NETWORK asset' USING ERRCODE = '23503';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER ned006b_validate_switching_config BEFORE INSERT OR UPDATE OR DELETE ON network_port_switching_configs
                FOR EACH ROW EXECUTE FUNCTION ned006b_validate_switching_config();

            CREATE OR REPLACE FUNCTION ned006b_validate_vlan_membership() RETURNS trigger AS $$
            DECLARE cfg record; vlan record;
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    RAISE EXCEPTION 'switching VLAN membership history cannot be deleted' USING ERRCODE = '23503';
                END IF;
                IF TG_OP = 'UPDATE' THEN
                    IF (OLD.network_port_switching_config_id, OLD.vlan_id, OLD.company_id, OLD.tagging)
                        IS DISTINCT FROM (NEW.network_port_switching_config_id, NEW.vlan_id, NEW.company_id, NEW.tagging) THEN
                        RAISE EXCEPTION 'switching VLAN membership identity is immutable' USING ERRCODE = '23503';
                    END IF;
                    IF OLD.deleted_at IS NOT NULL AND NEW.deleted_at IS NULL THEN
                        RAISE EXCEPTION 'switching VLAN membership history cannot be restored' USING ERRCODE = '23503';
                    END IF;
                    IF NEW.deleted_at IS NOT NULL THEN RETURN NEW; END IF;
                END IF;
                SELECT * INTO cfg FROM network_port_switching_configs WHERE id = NEW.network_port_switching_config_id;
                IF NOT FOUND THEN
                    RAISE EXCEPTION 'switching VLAN membership requires a valid switching configuration' USING ERRCODE = '23503';
                END IF;
                SELECT * INTO vlan FROM vlans WHERE id = NEW.vlan_id;
                IF NOT FOUND OR cfg.deleted_at IS NOT NULL OR vlan.deleted_at IS NOT NULL
                    OR cfg.company_id IS DISTINCT FROM NEW.company_id
                    OR vlan.company_id IS DISTINCT FROM NEW.company_id THEN
                    RAISE EXCEPTION 'switching VLAN membership requires live same-company configuration and VLAN' USING ERRCODE = '23503';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER ned006b_validate_vlan_membership BEFORE INSERT OR UPDATE OR DELETE ON network_port_vlan_memberships
                FOR EACH ROW EXECUTE FUNCTION ned006b_validate_vlan_membership();

            CREATE OR REPLACE FUNCTION ned006b_validate_switching_config_final() RETURNS trigger AS $$
            DECLARE config_id bigint; cfg record; membership_count bigint; untagged_count bigint;
            BEGIN
                config_id := COALESCE(NEW.id, OLD.id);
                SELECT * INTO cfg FROM network_port_switching_configs WHERE id = config_id;
                IF NOT FOUND THEN RETURN NULL; END IF;
                IF cfg.deleted_at IS NOT NULL THEN
                    IF EXISTS (SELECT 1 FROM network_port_vlan_memberships WHERE network_port_switching_config_id = config_id AND deleted_at IS NULL) THEN
                        RAISE EXCEPTION 'cannot retire switching configuration with live VLAN memberships' USING ERRCODE = '23514';
                    END IF;
                    RETURN NULL;
                END IF;
                SELECT count(*), count(*) FILTER (WHERE tagging = 'untagged') INTO membership_count, untagged_count
                    FROM network_port_vlan_memberships WHERE network_port_switching_config_id = config_id AND deleted_at IS NULL;
                IF cfg.mode = 'access' AND (membership_count <> 1 OR untagged_count <> 1) THEN
                    RAISE EXCEPTION 'access switching configuration requires exactly one untagged VLAN membership' USING ERRCODE = '23514';
                END IF;
                IF cfg.mode = 'trunk' AND untagged_count > 1 THEN
                    RAISE EXCEPTION 'trunk switching configuration permits at most one untagged VLAN membership' USING ERRCODE = '23514';
                END IF;
                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;

            CREATE OR REPLACE FUNCTION ned006b_validate_membership_final() RETURNS trigger AS $$
            DECLARE config_id bigint; cfg record; membership_count bigint; untagged_count bigint;
            BEGIN
                config_id := COALESCE(NEW.network_port_switching_config_id, OLD.network_port_switching_config_id);
                SELECT * INTO cfg FROM network_port_switching_configs WHERE id = config_id;
                IF NOT FOUND THEN RETURN NULL; END IF;
                IF cfg.deleted_at IS NOT NULL THEN
                    IF EXISTS (SELECT 1 FROM network_port_vlan_memberships WHERE network_port_switching_config_id = config_id AND deleted_at IS NULL) THEN
                        RAISE EXCEPTION 'cannot retain live VLAN membership on retired switching configuration' USING ERRCODE = '23514';
                    END IF;
                    RETURN NULL;
                END IF;
                SELECT count(*), count(*) FILTER (WHERE tagging = 'untagged') INTO membership_count, untagged_count
                    FROM network_port_vlan_memberships WHERE network_port_switching_config_id = config_id AND deleted_at IS NULL;
                IF cfg.mode = 'access' AND (membership_count <> 1 OR untagged_count <> 1) THEN
                    RAISE EXCEPTION 'access switching configuration requires exactly one untagged VLAN membership' USING ERRCODE = '23514';
                END IF;
                IF cfg.mode = 'trunk' AND untagged_count > 1 THEN
                    RAISE EXCEPTION 'trunk switching configuration permits at most one untagged VLAN membership' USING ERRCODE = '23514';
                END IF;
                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;

            CREATE CONSTRAINT TRIGGER ned006b_switching_config_final_trigger
                AFTER INSERT OR UPDATE ON network_port_switching_configs DEFERRABLE INITIALLY DEFERRED
                FOR EACH ROW EXECUTE FUNCTION ned006b_validate_switching_config_final();
            CREATE CONSTRAINT TRIGGER ned006b_membership_final_trigger
                AFTER INSERT OR UPDATE ON network_port_vlan_memberships DEFERRABLE INITIALLY DEFERRED
                FOR EACH ROW EXECUTE FUNCTION ned006b_validate_membership_final();

            CREATE OR REPLACE FUNCTION ned006b_protect_port_switching_history() RETURNS trigger AS $$
            DECLARE protected boolean; changed boolean;
            BEGIN
                SELECT EXISTS (SELECT 1 FROM network_port_switching_configs WHERE network_port_id = OLD.id) INTO protected;
                IF TG_OP = 'UPDATE' THEN
                    changed := (OLD.asset_id, OLD.company_id, OLD.port_key) IS DISTINCT FROM (NEW.asset_id, NEW.company_id, NEW.port_key)
                        OR (OLD.deleted_at IS NULL AND NEW.deleted_at IS NOT NULL);
                END IF;
                IF protected AND (TG_OP = 'DELETE' OR changed) THEN
                    RAISE EXCEPTION 'network port has switching configuration history' USING ERRCODE = '23503';
                END IF;
                IF TG_OP = 'DELETE' THEN RETURN OLD; END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER ned006b_protect_port_switching_history BEFORE UPDATE OR DELETE ON network_ports
                FOR EACH ROW EXECUTE FUNCTION ned006b_protect_port_switching_history();

            CREATE OR REPLACE FUNCTION ned006b_prevent_vlan_retirement() RETURNS trigger AS $$
            BEGIN
                IF OLD.deleted_at IS NULL AND NEW.deleted_at IS NOT NULL
                    AND EXISTS (SELECT 1 FROM network_port_vlan_memberships WHERE vlan_id = OLD.id AND deleted_at IS NULL) THEN
                    RAISE EXCEPTION 'cannot retire VLAN with live switching memberships' USING ERRCODE = '23503';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER ned006b_prevent_vlan_retirement BEFORE UPDATE OF deleted_at ON vlans
                FOR EACH ROW EXECUTE FUNCTION ned006b_prevent_vlan_retirement();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS ned006b_validate_switching_config ON network_port_switching_configs;
            DROP TRIGGER IF EXISTS ned006b_validate_vlan_membership ON network_port_vlan_memberships;
            DROP TRIGGER IF EXISTS ned006b_switching_config_final_trigger ON network_port_switching_configs;
            DROP TRIGGER IF EXISTS ned006b_membership_final_trigger ON network_port_vlan_memberships;
            DROP TRIGGER IF EXISTS ned006b_protect_port_switching_history ON network_ports;
            DROP TRIGGER IF EXISTS ned006b_prevent_vlan_retirement ON vlans;
            DROP FUNCTION IF EXISTS ned006b_validate_switching_config();
            DROP FUNCTION IF EXISTS ned006b_validate_vlan_membership();
            DROP FUNCTION IF EXISTS ned006b_validate_switching_config_final();
            DROP FUNCTION IF EXISTS ned006b_validate_membership_final();
            DROP FUNCTION IF EXISTS ned006b_protect_port_switching_history();
            DROP FUNCTION IF EXISTS ned006b_prevent_vlan_retirement();
            SQL);
        Schema::dropIfExists('network_port_vlan_memberships');
        Schema::dropIfExists('network_port_switching_configs');
    }
};
