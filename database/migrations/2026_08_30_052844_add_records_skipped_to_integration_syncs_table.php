<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integration_syncs', function (Blueprint $table) {
            $table->unsignedInteger('records_skipped')->default(0)->after('records_unchanged');
        });
    }

    public function down(): void
    {
        Schema::table('integration_syncs', function (Blueprint $table) {
            $table->dropColumn('records_skipped');
        });
    }
};
