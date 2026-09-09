<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('network_ports', function (Blueprint $table) {
            $table->id();

            $table->foreignId('asset_id')->constrained('assets')->restrictOnDelete();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();

            // Canonical physical port identity within the asset (vendor-supplied, immutable).
            $table->string('port_key');
            $table->string('name')->nullable();

            // Descriptive physical hierarchy (not universally required, never inferred).
            $table->string('slot', 50)->nullable();
            $table->string('card', 50)->nullable();
            $table->string('port_number', 50)->nullable();

            // Port characteristics.
            $table->string('connector_type', 50)->nullable();
            $table->string('port_direction', 20)->nullable();

            $table->jsonb('metadata')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('network_ports', function (Blueprint $table) {
            // Canonical identity: one port_key per asset, including soft-deleted history.
            DB::statement('CREATE UNIQUE INDEX network_ports_asset_port_key_unique ON network_ports (asset_id, port_key) WHERE deleted_at IS NULL');

            $table->index('company_id');
            $table->index('connector_type');
            $table->index('port_direction');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_ports');
    }
};
