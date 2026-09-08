<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE fiber_terminations ADD CONSTRAINT fiber_terminations_id_company_unique UNIQUE (id, company_id)');
        Schema::create('physical_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('termination_a_id')->constrained('fiber_terminations')->restrictOnDelete();
            $table->foreignId('termination_b_id')->constrained('fiber_terminations')->restrictOnDelete();
            $table->string('connection_type', 30);
            $table->jsonb('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index('company_id');
            $table->index('termination_a_id');
            $table->index('termination_b_id');
        });
        DB::statement('ALTER TABLE physical_connections ADD CONSTRAINT physical_connections_a_company_foreign FOREIGN KEY (termination_a_id, company_id) REFERENCES fiber_terminations (id, company_id) ON DELETE RESTRICT');
        DB::statement('ALTER TABLE physical_connections ADD CONSTRAINT physical_connections_b_company_foreign FOREIGN KEY (termination_b_id, company_id) REFERENCES fiber_terminations (id, company_id) ON DELETE RESTRICT');
        DB::statement('ALTER TABLE physical_connections ADD CONSTRAINT physical_connections_canonical_check CHECK (termination_a_id < termination_b_id)');
        DB::statement("ALTER TABLE physical_connections ADD CONSTRAINT physical_connections_type_check CHECK (connection_type IN ('fusion_splice', 'mechanical_splice'))");
        DB::statement('CREATE UNIQUE INDEX physical_connections_live_pair_unique ON physical_connections (termination_a_id, termination_b_id) WHERE deleted_at IS NULL');
        DB::unprepared('DROP FUNCTION IF EXISTS validate_physical_connection(); DROP FUNCTION IF EXISTS prevent_referenced_fiber_termination_soft_delete(); DROP FUNCTION IF EXISTS fim_validate_physical_connection(); DROP FUNCTION IF EXISTS fim_prevent_referenced_fiber_termination_soft_delete();');
        DB::unprepared(<<<'SQL'
            CREATE FUNCTION fim_validate_physical_connection() RETURNS trigger AS $$
            DECLARE a record; b record;
            BEGIN
                IF NEW.deleted_at IS NOT NULL THEN RETURN NEW; END IF;
                SELECT fiber_core_id, company_id, network_connection_point_id, deleted_at INTO a FROM fiber_terminations WHERE id=NEW.termination_a_id;
                SELECT fiber_core_id, company_id, network_connection_point_id, deleted_at INTO b FROM fiber_terminations WHERE id=NEW.termination_b_id;
                IF a.deleted_at IS NOT NULL OR b.deleted_at IS NOT NULL OR a.fiber_core_id=b.fiber_core_id OR a.company_id<>NEW.company_id OR b.company_id<>NEW.company_id OR a.network_connection_point_id<>b.network_connection_point_id THEN RAISE EXCEPTION 'invalid physical connection endpoints' USING ERRCODE='23503'; END IF;
                IF EXISTS (SELECT 1 FROM physical_connections WHERE deleted_at IS NULL AND id<>COALESCE(NEW.id, 0) AND (termination_a_id IN (NEW.termination_a_id,NEW.termination_b_id) OR termination_b_id IN (NEW.termination_a_id,NEW.termination_b_id))) THEN RAISE EXCEPTION 'fiber termination already has a live physical connection' USING ERRCODE='23505'; END IF;
                RETURN NEW;
            END; $$ LANGUAGE plpgsql;
            CREATE TRIGGER fim_validate_physical_connection_trigger BEFORE INSERT OR UPDATE OF termination_a_id, termination_b_id, company_id, deleted_at ON physical_connections FOR EACH ROW EXECUTE FUNCTION fim_validate_physical_connection();
            CREATE FUNCTION fim_prevent_referenced_fiber_termination_soft_delete() RETURNS trigger AS $$ BEGIN IF NEW.deleted_at IS NOT NULL AND OLD.deleted_at IS NULL AND EXISTS (SELECT 1 FROM physical_connections WHERE termination_a_id=OLD.id OR termination_b_id=OLD.id) THEN RAISE EXCEPTION 'fiber termination has physical connection history' USING ERRCODE='23503'; END IF; RETURN NEW; END; $$ LANGUAGE plpgsql;
            CREATE TRIGGER fim_prevent_referenced_fiber_termination_soft_delete_trigger BEFORE UPDATE OF deleted_at ON fiber_terminations FOR EACH ROW EXECUTE FUNCTION fim_prevent_referenced_fiber_termination_soft_delete();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS fim_validate_physical_connection_trigger ON physical_connections; DROP FUNCTION IF EXISTS fim_validate_physical_connection(); DROP TRIGGER IF EXISTS fim_prevent_referenced_fiber_termination_soft_delete_trigger ON fiber_terminations; DROP FUNCTION IF EXISTS fim_prevent_referenced_fiber_termination_soft_delete();');
        Schema::dropIfExists('physical_connections');
        DB::statement('ALTER TABLE fiber_terminations DROP CONSTRAINT fiber_terminations_id_company_unique');
    }
};
