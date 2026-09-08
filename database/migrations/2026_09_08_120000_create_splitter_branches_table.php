<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS fim_prevent_branched_profile_soft_delete() CASCADE; DROP FUNCTION IF EXISTS fim_prevent_branched_port_soft_delete() CASCADE; DROP FUNCTION IF EXISTS fim_validate_splitter_branch() CASCADE;');

        Schema::create('splitter_branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('splitter_profile_id')->constrained('splitter_profiles')->restrictOnDelete();
            $table->foreignId('asset_id')->constrained('assets')->restrictOnDelete();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('input_port_id')->constrained('passive_optical_ports')->restrictOnDelete();
            $table->foreignId('output_port_id')->constrained('passive_optical_ports')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('company_id');
            $table->index('input_port_id');
        });

        DB::statement('ALTER TABLE splitter_branches ADD CONSTRAINT splitter_branches_asset_company_foreign FOREIGN KEY (asset_id, company_id) REFERENCES assets (id, company_id) ON DELETE RESTRICT');
        DB::statement('ALTER TABLE splitter_branches ADD CONSTRAINT splitter_branches_input_not_output CHECK (input_port_id <> output_port_id)');
        DB::statement('CREATE UNIQUE INDEX splitter_branches_output_port_unique ON splitter_branches (output_port_id) WHERE deleted_at IS NULL');

        DB::unprepared(<<<'SQL'
            CREATE FUNCTION fim_validate_splitter_branch() RETURNS trigger AS $$
            DECLARE profile_record record; input_port_record record; output_port_record record;
            BEGIN
                SELECT id, asset_id, company_id, deleted_at INTO STRICT profile_record FROM splitter_profiles WHERE id = NEW.splitter_profile_id;
                IF profile_record.deleted_at IS NOT NULL THEN
                    RAISE EXCEPTION 'splitter branch requires a live splitter profile' USING ERRCODE = '23514';
                END IF;
                IF profile_record.asset_id <> NEW.asset_id OR profile_record.company_id <> NEW.company_id THEN
                    RAISE EXCEPTION 'splitter branch profile asset/company mismatch' USING ERRCODE = '23514';
                END IF;

                SELECT id, asset_id, company_id, port_role, deleted_at INTO STRICT input_port_record FROM passive_optical_ports WHERE id = NEW.input_port_id;
                IF input_port_record.deleted_at IS NOT NULL THEN
                    RAISE EXCEPTION 'splitter branch input port must be live' USING ERRCODE = '23514';
                END IF;
                IF input_port_record.asset_id <> NEW.asset_id OR input_port_record.company_id <> NEW.company_id THEN
                    RAISE EXCEPTION 'splitter branch input port asset/company mismatch' USING ERRCODE = '23514';
                END IF;
                IF input_port_record.port_role <> 'splitter_input' THEN
                    RAISE EXCEPTION 'splitter branch input port must have splitter_input role' USING ERRCODE = '23514';
                END IF;

                SELECT id, asset_id, company_id, port_role, deleted_at INTO STRICT output_port_record FROM passive_optical_ports WHERE id = NEW.output_port_id;
                IF output_port_record.deleted_at IS NOT NULL THEN
                    RAISE EXCEPTION 'splitter branch output port must be live' USING ERRCODE = '23514';
                END IF;
                IF output_port_record.asset_id <> NEW.asset_id OR output_port_record.company_id <> NEW.company_id THEN
                    RAISE EXCEPTION 'splitter branch output port asset/company mismatch' USING ERRCODE = '23514';
                END IF;
                IF output_port_record.port_role <> 'splitter_output' THEN
                    RAISE EXCEPTION 'splitter branch output port must have splitter_output role' USING ERRCODE = '23514';
                END IF;

                IF NEW.input_port_id = NEW.output_port_id THEN
                    RAISE EXCEPTION 'splitter branch input and output ports must differ' USING ERRCODE = '23514';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER fim_validate_splitter_branch_trigger BEFORE INSERT OR UPDATE OF splitter_profile_id, asset_id, company_id, input_port_id, output_port_id ON splitter_branches
                FOR EACH ROW EXECUTE FUNCTION fim_validate_splitter_branch();

            CREATE FUNCTION fim_prevent_branched_port_soft_delete() RETURNS trigger AS $$
            BEGIN
                IF NEW.deleted_at IS NOT NULL AND OLD.deleted_at IS NULL
                    AND EXISTS (SELECT 1 FROM splitter_branches WHERE input_port_id = OLD.id OR output_port_id = OLD.id) THEN
                    RAISE EXCEPTION 'passive optical port has splitter branches' USING ERRCODE = '23514';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER fim_prevent_branched_port_soft_delete_trigger BEFORE UPDATE OF deleted_at ON passive_optical_ports
                FOR EACH ROW EXECUTE FUNCTION fim_prevent_branched_port_soft_delete();

            CREATE FUNCTION fim_prevent_branched_profile_soft_delete() RETURNS trigger AS $$
            BEGIN
                IF NEW.deleted_at IS NOT NULL AND OLD.deleted_at IS NULL
                    AND EXISTS (SELECT 1 FROM splitter_branches WHERE splitter_profile_id = OLD.id AND deleted_at IS NULL) THEN
                    RAISE EXCEPTION 'splitter profile has live splitter branches' USING ERRCODE = '23514';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER fim_prevent_branched_profile_soft_delete_trigger BEFORE UPDATE OF deleted_at ON splitter_profiles
                FOR EACH ROW EXECUTE FUNCTION fim_prevent_branched_profile_soft_delete();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS fim_prevent_branched_profile_soft_delete_trigger ON splitter_profiles; DROP FUNCTION IF EXISTS fim_prevent_branched_profile_soft_delete(); DROP TRIGGER IF EXISTS fim_prevent_branched_port_soft_delete_trigger ON passive_optical_ports; DROP FUNCTION IF EXISTS fim_prevent_branched_port_soft_delete(); DROP TRIGGER IF EXISTS fim_validate_splitter_branch_trigger ON splitter_branches; DROP FUNCTION IF EXISTS fim_validate_splitter_branch();');
        Schema::dropIfExists('splitter_branches');
    }
};
