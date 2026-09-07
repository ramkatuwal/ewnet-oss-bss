<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE fiber_segments ADD CONSTRAINT fiber_segments_id_company_unique UNIQUE (id, company_id)');

        Schema::create('fiber_cores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiber_segment_id')->constrained('fiber_segments')->restrictOnDelete();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->unsignedInteger('core_number');
            $table->string('status', 50);
            $table->string('color_code', 50)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['fiber_segment_id', 'core_number']);
            $table->index('company_id');
            $table->index('status');
        });

        DB::statement('ALTER TABLE fiber_cores ADD CONSTRAINT fiber_cores_segment_company_foreign FOREIGN KEY (fiber_segment_id, company_id) REFERENCES fiber_segments (id, company_id) ON DELETE RESTRICT');
        DB::statement('ALTER TABLE fiber_cores ADD CONSTRAINT fiber_cores_core_number_positive CHECK (core_number > 0)');

        // A live core cannot retain a soft-deleted segment as its physical parent.
        DB::unprepared('DROP TRIGGER IF EXISTS prevent_referenced_fiber_segment_soft_delete_trigger ON fiber_segments; DROP FUNCTION IF EXISTS prevent_referenced_fiber_segment_soft_delete();');
        DB::unprepared(<<<'SQL'
            CREATE FUNCTION prevent_referenced_fiber_segment_soft_delete() RETURNS trigger AS $$
            BEGIN
                IF NEW.deleted_at IS NOT NULL AND OLD.deleted_at IS NULL
                    AND EXISTS (
                        SELECT 1 FROM fiber_cores
                        WHERE fiber_segment_id = OLD.id AND deleted_at IS NULL
                    ) THEN
                    RAISE EXCEPTION 'fiber segment % is referenced by a live fiber core', OLD.id
                        USING ERRCODE = '23503';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER prevent_referenced_fiber_segment_soft_delete_trigger
                BEFORE UPDATE OF deleted_at ON fiber_segments
                FOR EACH ROW EXECUTE FUNCTION prevent_referenced_fiber_segment_soft_delete();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS prevent_referenced_fiber_segment_soft_delete_trigger ON fiber_segments; DROP FUNCTION IF EXISTS prevent_referenced_fiber_segment_soft_delete();');
        Schema::dropIfExists('fiber_cores');
        DB::statement('ALTER TABLE fiber_segments DROP CONSTRAINT fiber_segments_id_company_unique');
    }
};
