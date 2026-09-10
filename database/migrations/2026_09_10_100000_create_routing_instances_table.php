<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routing_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->restrictOnDelete();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('name', 100);
            $table->string('kind', 20);
            $table->jsonb('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement("ALTER TABLE routing_instances ADD CONSTRAINT routing_instances_kind_check CHECK (kind IN ('default', 'vrf'))");
        DB::statement('ALTER TABLE routing_instances ADD CONSTRAINT routing_instances_name_nonempty_check CHECK (length(btrim(name)) > 0)');
        DB::statement('CREATE UNIQUE INDEX routing_instances_live_asset_name_unique ON routing_instances (asset_id, name) WHERE deleted_at IS NULL');
        DB::statement("CREATE UNIQUE INDEX routing_instances_live_default_asset_unique ON routing_instances (asset_id) WHERE deleted_at IS NULL AND kind = 'default'");
        DB::statement('CREATE INDEX routing_instances_company_id_index ON routing_instances (company_id)');

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION ned007a_validate_routing_instance() RETURNS trigger AS $$
            DECLARE asset record;
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    RAISE EXCEPTION 'routing instance history cannot be deleted' USING ERRCODE = '23503';
                END IF;
                IF TG_OP = 'UPDATE' THEN
                    IF (OLD.asset_id, OLD.company_id, OLD.name, OLD.kind)
                        IS DISTINCT FROM (NEW.asset_id, NEW.company_id, NEW.name, NEW.kind) THEN
                        RAISE EXCEPTION 'routing instance identity is immutable' USING ERRCODE = '23503';
                    END IF;
                    IF OLD.deleted_at IS NOT NULL AND NEW.deleted_at IS NULL THEN
                        RAISE EXCEPTION 'routing instance history cannot be restored' USING ERRCODE = '23503';
                    END IF;
                    IF NEW.deleted_at IS NOT NULL THEN RETURN NEW; END IF;
                END IF;
                SELECT * INTO asset FROM assets WHERE id = NEW.asset_id FOR KEY SHARE;
                IF NOT FOUND OR asset.deleted_at IS NOT NULL
                    OR UPPER(asset.category) IS DISTINCT FROM 'NETWORK'
                    OR asset.company_id IS NULL
                    OR asset.company_id IS DISTINCT FROM NEW.company_id THEN
                    RAISE EXCEPTION 'routing instance requires a live same-company NETWORK asset' USING ERRCODE = '23503';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER ned007a_validate_routing_instance BEFORE INSERT OR UPDATE OR DELETE ON routing_instances
                FOR EACH ROW EXECUTE FUNCTION ned007a_validate_routing_instance();

            CREATE OR REPLACE FUNCTION ned007a_protect_routing_instance_asset_history() RETURNS trigger AS $$
            DECLARE protected boolean; changed boolean;
            BEGIN
                SELECT EXISTS (SELECT 1 FROM routing_instances WHERE asset_id = OLD.id) INTO protected;
                IF TG_OP = 'UPDATE' THEN
                    changed := (OLD.company_id, OLD.category) IS DISTINCT FROM (NEW.company_id, NEW.category)
                        OR (OLD.deleted_at IS NULL AND NEW.deleted_at IS NOT NULL);
                END IF;
                IF protected AND (TG_OP = 'DELETE' OR changed) THEN
                    RAISE EXCEPTION 'asset has routing instance history' USING ERRCODE = '23503';
                END IF;
                IF TG_OP = 'DELETE' THEN RETURN OLD; END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER ned007a_protect_routing_instance_asset_history BEFORE UPDATE OR DELETE ON assets
                FOR EACH ROW EXECUTE FUNCTION ned007a_protect_routing_instance_asset_history();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS ned007a_validate_routing_instance ON routing_instances;
            DROP TRIGGER IF EXISTS ned007a_protect_routing_instance_asset_history ON assets;
            DROP FUNCTION IF EXISTS ned007a_validate_routing_instance();
            DROP FUNCTION IF EXISTS ned007a_protect_routing_instance_asset_history();
            SQL);
        Schema::dropIfExists('routing_instances');
    }
};
