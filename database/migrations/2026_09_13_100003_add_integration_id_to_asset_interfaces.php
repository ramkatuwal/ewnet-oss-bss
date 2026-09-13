<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_interfaces', function (Blueprint $table): void {
            $table->foreignId('integration_id')->nullable()->after('provider')->constrained('integrations')->nullOnDelete();
            $table->index(['integration_id', 'asset_id', 'external_type', 'external_id'], 'asset_interfaces_observation_identity_idx');
        });
    }

    public function down(): void
    {
        Schema::table('asset_interfaces', function (Blueprint $table): void {
            $table->dropIndex('asset_interfaces_observation_identity_idx');
            $table->dropConstrainedForeignId('integration_id');
        });
    }
};
