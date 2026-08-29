<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('provider'); // e.g., 'librenms', 'radius', 'juniper'
            $table->string('type'); // monitoring, aaa, network_device, access_network, dns, dhcp, logging, authentication, billing, other
            $table->text('description')->nullable();
            $table->boolean('enabled')->default(false);
            $table->string('status')->default('unknown'); // unknown, pending, connected, degraded, failed, disabled
            $table->json('configuration')->nullable(); // non-sensitive config (endpoint, port, timeout, etc.)
            $table->timestamp('last_health_check_at')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('provider');
            $table->index('type');
            $table->index('enabled');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integrations');
    }
};
