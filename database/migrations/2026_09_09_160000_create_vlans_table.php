<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vlans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->smallInteger('vid');
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('reserved')->default(false);
            $table->jsonb('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('ALTER TABLE vlans ADD CONSTRAINT vlans_vid_range_check CHECK (vid BETWEEN 1 AND 4094)');
        DB::statement('CREATE UNIQUE INDEX vlans_live_company_vid_unique ON vlans (company_id, vid) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX vlans_company_id_index ON vlans (company_id)');

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION ned006a_validate_vlan_history() RETURNS trigger AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    RAISE EXCEPTION 'vlan history cannot be deleted' USING ERRCODE = '23503';
                END IF;
                IF TG_OP = 'UPDATE' THEN
                    IF (OLD.company_id, OLD.vid) IS DISTINCT FROM (NEW.company_id, NEW.vid) THEN
                        RAISE EXCEPTION 'vlan company_id and vid are immutable' USING ERRCODE = '23503';
                    END IF;
                    IF OLD.deleted_at IS NOT NULL AND NEW.deleted_at IS NULL THEN
                        RAISE EXCEPTION 'vlan history cannot be restored; create a new vlan identity' USING ERRCODE = '23503';
                    END IF;
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER ned006a_validate_vlan_history
                BEFORE UPDATE OR DELETE ON vlans
                FOR EACH ROW EXECUTE FUNCTION ned006a_validate_vlan_history();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS ned006a_validate_vlan_history ON vlans;
            DROP FUNCTION IF EXISTS ned006a_validate_vlan_history();
            SQL);

        Schema::dropIfExists('vlans');
    }
};
