<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Observed (provider/discovery) VLAN data. Strictly read-only observations
     * that are reconciled to the authoritative `vlans` table via
     * `reconciled_vlan_id`. Never authored through this table.
     */
    public function up(): void
    {
        Schema::create('observed_vlans', function (Blueprint $table) {
            $table->id();

            $table->foreignId('integration_id')
                ->constrained('integrations')
                ->onDelete('cascade');

            $table->foreignId('asset_id')
                ->constrained('assets')
                ->onDelete('cascade');

            // Last interface the VLAN was observed on (e.g. access VLAN).
            $table->foreignId('asset_interface_id')
                ->nullable()
                ->constrained('asset_interfaces')
                ->onDelete('set null');

            $table->smallInteger('vid');
            $table->string('name')->nullable();
            $table->string('vlan_type')->nullable(); // vlanif, bridge, access, ...
            $table->string('provider')->nullable();  // librenms, uisp
            $table->string('external_type')->nullable(); // device, interface
            $table->string('external_id')->nullable();   // provider vlan id when available

            // observed | stale — stale means the provider stopped reporting it.
            $table->string('observation_status', 16)->default('observed');

            // Source / observation context
            $table->jsonb('metadata')->nullable();

            // Reconciliation to the authoritative VLAN catalog (read-only link).
            $table->foreignId('reconciled_vlan_id')
                ->nullable()
                ->constrained('vlans')
                ->onDelete('set null');

            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index('integration_id');
            $table->index('asset_id');
            $table->index(['asset_id', 'vid']);
            $table->index('asset_interface_id');
            $table->index(['asset_id', 'observation_status']);
            $table->index('reconciled_vlan_id');

            // One observed VLAN per integration + asset + VID (aggregated identity).
            $table->unique(['integration_id', 'asset_id', 'vid'], 'observed_vlans_integration_asset_vid_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('observed_vlans');
    }
};
