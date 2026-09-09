<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pon_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('olt_port_id')->constrained('network_ports')->restrictOnDelete();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->jsonb('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('CREATE UNIQUE INDEX pon_domains_live_port_unique ON pon_domains (olt_port_id) WHERE deleted_at IS NULL');

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION ned005b_validate_pon_domain() RETURNS trigger AS $$
            DECLARE port record; asset record;
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    RAISE EXCEPTION 'pon domain history cannot be deleted' USING ERRCODE = '23503';
                END IF;
                IF TG_OP = 'UPDATE' THEN
                    IF (OLD.olt_port_id, OLD.company_id) IS DISTINCT FROM (NEW.olt_port_id, NEW.company_id) THEN
                        RAISE EXCEPTION 'pon domain identity is immutable' USING ERRCODE = '23503';
                    END IF;
                    IF OLD.deleted_at IS NOT NULL AND NEW.deleted_at IS NULL THEN
                        RAISE EXCEPTION 'reconnect requires a new pon domain' USING ERRCODE = '23503';
                    END IF;
                    IF NEW.deleted_at IS NOT NULL THEN RETURN NEW; END IF;
                END IF;
                SELECT * INTO port FROM network_ports WHERE id = NEW.olt_port_id;
                IF NOT FOUND THEN
                    RAISE EXCEPTION 'pon domain requires a valid network port' USING ERRCODE = '23503';
                END IF;
                SELECT * INTO asset FROM assets WHERE id = port.asset_id;
                IF NOT FOUND THEN
                    RAISE EXCEPTION 'pon domain requires a valid parent asset' USING ERRCODE = '23503';
                END IF;
                IF port.deleted_at IS NOT NULL
                    OR asset.deleted_at IS NOT NULL
                    OR UPPER(asset.category) IS DISTINCT FROM 'NETWORK'
                    OR UPPER(asset.type) IS DISTINCT FROM 'OLT'
                    OR port.technology IS NULL
                    OR port.company_id IS DISTINCT FROM NEW.company_id
                    OR asset.company_id IS DISTINCT FROM NEW.company_id THEN
                    RAISE EXCEPTION 'pon domain requires live same-company OLT port with technology on NETWORK/OLT asset' USING ERRCODE = '23503';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER ned005b_validate_pon_domain BEFORE INSERT OR UPDATE OR DELETE ON pon_domains
                FOR EACH ROW EXECUTE FUNCTION ned005b_validate_pon_domain();

            CREATE OR REPLACE FUNCTION ned005b_protect_pon_domain_history() RETURNS trigger AS $$
            DECLARE protected boolean; changed boolean;
            BEGIN
                SELECT EXISTS (SELECT 1 FROM pon_domains WHERE olt_port_id = OLD.id) INTO protected;
                IF TG_OP = 'UPDATE' THEN
                    changed := (OLD.id, OLD.asset_id, OLD.company_id, OLD.port_key, OLD.technology)
                        IS DISTINCT FROM (NEW.id, NEW.asset_id, NEW.company_id, NEW.port_key, NEW.technology)
                        OR (OLD.deleted_at IS NULL AND NEW.deleted_at IS NOT NULL);
                END IF;
                IF protected AND (TG_OP = 'DELETE' OR changed) THEN
                    RAISE EXCEPTION 'network port has pon domain history' USING ERRCODE = '23503';
                END IF;
                IF TG_OP = 'DELETE' THEN RETURN OLD; END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER ned005b_protect_pon_domain_history BEFORE UPDATE OR DELETE ON network_ports
                FOR EACH ROW EXECUTE FUNCTION ned005b_protect_pon_domain_history();

            CREATE OR REPLACE FUNCTION ned005b_protect_olt_asset_history() RETURNS trigger AS $$
            DECLARE protected boolean; changed boolean;
            BEGIN
                SELECT EXISTS (
                    SELECT 1 FROM pon_domains pd
                    JOIN network_ports np ON np.id = pd.olt_port_id
                    WHERE np.asset_id = OLD.id
                ) INTO protected;
                IF TG_OP = 'UPDATE' THEN
                    changed := (OLD.company_id, OLD.category, OLD.type)
                        IS DISTINCT FROM (NEW.company_id, NEW.category, NEW.type)
                        OR (OLD.deleted_at IS NULL AND NEW.deleted_at IS NOT NULL);
                END IF;
                IF protected AND (TG_OP = 'DELETE' OR changed) THEN
                    RAISE EXCEPTION 'OLT asset has pon domain history' USING ERRCODE = '23503';
                END IF;
                IF TG_OP = 'DELETE' THEN RETURN OLD; END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER ned005b_protect_olt_asset_history BEFORE UPDATE OR DELETE ON assets
                FOR EACH ROW EXECUTE FUNCTION ned005b_protect_olt_asset_history();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS ned005b_validate_pon_domain ON pon_domains;
            DROP TRIGGER IF EXISTS ned005b_protect_pon_domain_history ON network_ports;
            DROP TRIGGER IF EXISTS ned005b_protect_olt_asset_history ON assets;
            DROP FUNCTION IF EXISTS ned005b_validate_pon_domain();
            DROP FUNCTION IF EXISTS ned005b_protect_pon_domain_history();
            DROP FUNCTION IF EXISTS ned005b_protect_olt_asset_history();
            SQL);
        Schema::dropIfExists('pon_domains');
    }
};
