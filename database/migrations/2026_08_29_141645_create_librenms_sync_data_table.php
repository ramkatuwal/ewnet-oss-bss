<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Stores synchronized LibreNMS objects (devices, ports, alerts, pollers)
        // Preserves external identity for future FIM correlation (TASK-036)
        Schema::create('librenms_objects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_id')->constrained('integrations')->cascadeOnDelete();
            $table->string('object_type'); // device, port, alert, poller
            $table->string('external_id'); // LibreNMS device_id, port_id, alert_id, poller_id
            $table->string('external_parent_id')->nullable(); // device_id for ports
            $table->json('data'); // Full normalized object data from LibreNMS
            $table->string('display_name')->nullable(); // hostname, ifName, etc.
            $table->string('status')->nullable(); // up/down/warning for devices, operStatus for ports
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            // Unique constraint ensures idempotency: one record per integration+type+external_id
            $table->unique(['integration_id', 'object_type', 'external_id']);
            $table->index('object_type');
            $table->index('external_parent_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('librenms_objects');
    }
};
