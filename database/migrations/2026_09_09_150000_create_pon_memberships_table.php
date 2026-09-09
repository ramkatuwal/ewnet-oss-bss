<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pon_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pon_domain_id')->constrained('pon_domains')->restrictOnDelete();
            $table->foreignId('onu_asset_id')->constrained('assets')->restrictOnDelete();
            $table->string('onu_id', 100)->nullable();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->jsonb('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('CREATE UNIQUE INDEX pon_memberships_live_onu_asset_unique ON pon_memberships (onu_asset_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX pon_memberships_live_pon_onu_id_unique ON pon_memberships (pon_domain_id, onu_id) WHERE deleted_at IS NULL AND onu_id IS NOT NULL');

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION ned005c_validate_pon_membership() RETURNS trigger AS $$
            DECLARE dom record; olt_port record; olt_asset record; onu_asset record; cnt bigint;
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    RAISE EXCEPTION 'pon membership history cannot be deleted' USING ERRCODE = '23503';
                END IF;
                IF TG_OP = 'UPDATE' THEN
                    IF OLD.pon_domain_id IS DISTINCT FROM NEW.pon_domain_id
                        OR OLD.onu_asset_id IS DISTINCT FROM NEW.onu_asset_id
                        OR OLD.company_id IS DISTINCT FROM NEW.company_id THEN
                        RAISE EXCEPTION 'pon membership identity is immutable' USING ERRCODE = '23503';
                    END IF;
                    IF OLD.deleted_at IS NOT NULL AND NEW.deleted_at IS NULL THEN
                        RAISE EXCEPTION 'reconnect requires a new pon membership' USING ERRCODE = '23503';
                    END IF;
                    IF NEW.deleted_at IS NOT NULL THEN RETURN NEW; END IF;
                END IF;

                SELECT * INTO dom FROM pon_domains WHERE id = NEW.pon_domain_id;
                IF NOT FOUND OR dom.deleted_at IS NOT NULL THEN
                    RAISE EXCEPTION 'pon membership requires a live pon domain' USING ERRCODE = '23503';
                END IF;

                SELECT np.*, a.id AS asset_id, a.deleted_at AS asset_deleted_at, a.category AS asset_category, a.type AS asset_type, a.company_id AS asset_company_id
                INTO olt_port FROM network_ports np JOIN assets a ON a.id = np.asset_id WHERE np.id = dom.olt_port_id;
                IF NOT FOUND OR olt_port.deleted_at IS NOT NULL OR olt_port.asset_deleted_at IS NOT NULL THEN
                    RAISE EXCEPTION 'pon domain owning OLT port or asset is not live' USING ERRCode = '23503';
                END IF;

                SELECT * INTO onu_asset FROM assets WHERE id = NEW.onu_asset_id;
                IF NOT FOUND OR onu_asset.deleted_at IS NOT NULL THEN
                    RAISE EXCEPTION 'pon membership requires a live ONU asset' USING ERRCODE = '23503';
                END IF;
                IF UPPER(onu_asset.category) IS DISTINCT FROM 'NETWORK' OR UPPER(onu_asset.type) IS DISTINCT FROM 'ONU' THEN
                    RAISE EXCEPTION 'ONU asset must be category NETWORK type ONU' USING ERRCODE = '23503';
                END IF;
                IF NEW.company_id IS DISTINCT FROM dom.company_id
                    OR NEW.company_id IS DISTINCT FROM onu_asset.company_id THEN
                    RAISE EXCEPTION 'membership, pon domain, and onu asset must share the same company' USING ERRCODE = '23503';
                END IF;

                IF TG_OP = 'INSERT' OR (OLD.onu_id IS DISTINCT FROM NEW.onu_id AND NEW.onu_id IS NOT NULL) THEN
                    IF NEW.onu_id IS NOT NULL THEN
                        SELECT count(*) INTO cnt FROM pon_memberships WHERE pon_domain_id = NEW.pon_domain_id AND onu_id = NEW.onu_id AND deleted_at IS NULL AND id IS DISTINCT FROM NEW.id;
                        IF cnt > 0 THEN
                            RAISE EXCEPTION 'onu_id is already claimed on this pon domain' USING ERRCODE = '23503';
                        END IF;
                    END IF;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER ned005c_validate_pon_membership BEFORE INSERT OR UPDATE OR DELETE ON pon_memberships
                FOR EACH ROW EXECUTE FUNCTION ned005c_validate_pon_membership();

            CREATE OR REPLACE FUNCTION ned005c_protect_onu_asset_history() RETURNS trigger AS $$
            DECLARE protected boolean; changed boolean;
            BEGIN
                SELECT EXISTS (
                    SELECT 1 FROM pon_memberships WHERE onu_asset_id = OLD.id
                ) INTO protected;
                IF TG_OP = 'UPDATE' THEN
                    changed := (OLD.company_id, OLD.category, OLD.type)
                        IS DISTINCT FROM (NEW.company_id, NEW.category, NEW.type)
                        OR (OLD.deleted_at IS NULL AND NEW.deleted_at IS NOT NULL);
                END IF;
                IF protected AND (TG_OP = 'DELETE' OR changed) THEN
                    RAISE EXCEPTION 'ONU asset has pon membership history' USING ERRCODE = '23503';
                END IF;
                IF TG_OP = 'DELETE' THEN RETURN OLD; END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER ned005c_protect_onu_asset_history BEFORE UPDATE OR DELETE ON assets
                FOR EACH ROW EXECUTE FUNCTION ned005c_protect_onu_asset_history();

            CREATE OR REPLACE FUNCTION ned005c_protect_pon_domain_retirement() RETURNS trigger AS $$
            DECLARE live_count bigint;
            BEGIN
                IF TG_OP = 'UPDATE' AND OLD.deleted_at IS NULL AND NEW.deleted_at IS NOT NULL THEN
                    SELECT count(*) INTO live_count FROM pon_memberships WHERE pon_domain_id = OLD.id AND deleted_at IS NULL;
                    IF live_count > 0 THEN
                        RAISE EXCEPTION 'cannot retire pon domain with live memberships (% remaining)', live_count USING ERRCODE = '23503';
                    END IF;
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER ned005c_protect_pon_domain_retirement BEFORE UPDATE OF deleted_at ON pon_domains
                FOR EACH ROW EXECUTE FUNCTION ned005c_protect_pon_domain_retirement();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS ned005c_validate_pon_membership ON pon_memberships;
            DROP TRIGGER IF EXISTS ned005c_protect_onu_asset_history ON assets;
            DROP TRIGGER IF EXISTS ned005c_protect_pon_domain_retirement ON pon_domains;
            DROP FUNCTION IF EXISTS ned005c_validate_pon_membership();
            DROP FUNCTION IF EXISTS ned005c_protect_onu_asset_history();
            DROP FUNCTION IF EXISTS ned005c_protect_pon_domain_retirement();
            SQL);
        Schema::dropIfExists('pon_memberships');
    }
};
