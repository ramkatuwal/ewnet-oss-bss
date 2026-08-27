<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        // Drop foreign key first
        DB::statement("ALTER TABLE departments DROP CONSTRAINT IF EXISTS departments_manager_id_foreign;");
        // Drop column
        DB::statement("ALTER TABLE departments DROP COLUMN IF EXISTS manager_id;");
    }
    public function down(): void {
        // We won't implement down as this is a destructive purge task
    }
};
