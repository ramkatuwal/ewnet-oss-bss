<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_external_references', function (Blueprint $table) {
            // Step 1: Make integration_id nullable first (existing rows may not have it)
            if (!Schema::hasColumn('asset_external_references', 'integration_id')) {
                $table->foreignId('integration_id')->nullable()->constrained('integrations')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('asset_external_references', function (Blueprint $table) {
            if (Schema::hasColumn('asset_external_references', 'integration_id')) {
                $table->dropForeign(['integration_id']);
                $table->dropColumn('integration_id');
            }
        });
    }
};
