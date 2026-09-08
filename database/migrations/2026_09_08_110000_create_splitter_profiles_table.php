<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // PostgreSQL functions survive migrate:fresh table drops.
        DB::unprepared('DROP FUNCTION IF EXISTS fim_validate_splitter_profile(); DROP FUNCTION IF EXISTS fim_protect_splitter_profile_asset(); DROP FUNCTION IF EXISTS fim_lock_splitter_role_asset();');

        Schema::create('splitter_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->unique()->constrained('assets')->restrictOnDelete();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->integer('input_port_count');
            $table->integer('output_port_count');
            $table->string('split_ratio')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index('company_id');
        });

        DB::statement('ALTER TABLE splitter_profiles ADD CONSTRAINT splitter_profiles_asset_company_foreign FOREIGN KEY (asset_id, company_id) REFERENCES assets (id, company_id) ON DELETE RESTRICT');
        DB::statement('ALTER TABLE splitter_profiles ADD CONSTRAINT splitter_profiles_positive_counts CHECK (input_port_count > 0 AND output_port_count > 0)');

        DB::unprepared(<<<'SQL'
            CREATE FUNCTION fim_validate_splitter_profile() RETURNS trigger AS $$
            DECLARE parent record;
            BEGIN
                IF TG_OP = 'UPDATE' THEN
                    IF NEW.asset_id IS DISTINCT FROM OLD.asset_id OR NEW.company_id IS DISTINCT FROM OLD.company_id THEN
                        RAISE EXCEPTION 'splitter profile ownership is immutable' USING ERRCODE = '23514';
                    END IF;
                    IF OLD.deleted_at IS NOT NULL AND NEW.deleted_at IS NULL THEN
                        RAISE EXCEPTION 'splitter profile retirement is terminal' USING ERRCODE = '23514';
                    END IF;
                END IF;

                SELECT company_id, category, type, deleted_at INTO STRICT parent FROM assets WHERE id = NEW.asset_id FOR UPDATE;
                IF parent.deleted_at IS NOT NULL OR parent.company_id IS NULL
                    OR parent.company_id IS DISTINCT FROM NEW.company_id
                    OR parent.category IS DISTINCT FROM 'INFRASTRUCTURE'
                    OR upper(parent.type) IS DISTINCT FROM 'SPLITTER' THEN
                    RAISE EXCEPTION 'splitter profile requires an active explicit-company infrastructure splitter' USING ERRCODE = '23514';
                END IF;

                IF TG_OP = 'UPDATE' THEN
                    IF (NEW.input_port_count IS DISTINCT FROM OLD.input_port_count OR NEW.output_port_count IS DISTINCT FROM OLD.output_port_count)
                        AND EXISTS (SELECT 1 FROM passive_optical_ports WHERE asset_id = OLD.asset_id AND port_role IN ('splitter_input', 'splitter_output')) THEN
                        RAISE EXCEPTION 'splitter profile counts are frozen by port history' USING ERRCODE = '23514';
                    END IF;
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER fim_validate_splitter_profile_trigger BEFORE INSERT OR UPDATE ON splitter_profiles
                FOR EACH ROW EXECUTE FUNCTION fim_validate_splitter_profile();

            CREATE FUNCTION fim_protect_splitter_profile_asset() RETURNS trigger AS $$
            BEGIN
                IF EXISTS (SELECT 1 FROM splitter_profiles WHERE asset_id = OLD.id)
                    AND (NEW.deleted_at IS NOT NULL OR NEW.company_id IS DISTINCT FROM OLD.company_id
                        OR NEW.category IS DISTINCT FROM 'INFRASTRUCTURE' OR upper(NEW.type) IS DISTINCT FROM 'SPLITTER') THEN
                    RAISE EXCEPTION 'asset has splitter profile history' USING ERRCODE = '23514';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER fim_protect_splitter_profile_asset_trigger BEFORE UPDATE OF deleted_at, company_id, category, type ON assets
                FOR EACH ROW EXECUTE FUNCTION fim_protect_splitter_profile_asset();

            -- Serialize role history insertion with profile count changes, including direct SQL writers.
            CREATE FUNCTION fim_lock_splitter_role_asset() RETURNS trigger AS $$
            BEGIN
                IF NEW.port_role IN ('splitter_input', 'splitter_output') THEN
                    PERFORM id FROM assets WHERE id = NEW.asset_id FOR UPDATE;
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER fim_lock_splitter_role_asset_trigger BEFORE INSERT OR UPDATE OF asset_id, port_role ON passive_optical_ports
                FOR EACH ROW EXECUTE FUNCTION fim_lock_splitter_role_asset();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS fim_lock_splitter_role_asset_trigger ON passive_optical_ports; DROP FUNCTION IF EXISTS fim_lock_splitter_role_asset(); DROP TRIGGER IF EXISTS fim_protect_splitter_profile_asset_trigger ON assets; DROP FUNCTION IF EXISTS fim_protect_splitter_profile_asset(); DROP TRIGGER IF EXISTS fim_validate_splitter_profile_trigger ON splitter_profiles; DROP FUNCTION IF EXISTS fim_validate_splitter_profile();');
        Schema::dropIfExists('splitter_profiles');
    }
};
