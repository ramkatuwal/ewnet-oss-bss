<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        // Drop employees table
        DB::statement("DROP TABLE IF EXISTS employees CASCADE;");
        // Drop designations table
        DB::statement("DROP TABLE IF EXISTS designations CASCADE;");
    }
    public function down(): void {
        // We won't implement down as this is a destructive purge task
    }
};
