<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_interfaces', function (Blueprint $table) {
            $table->id();
            
            // Asset relationship
            $table->foreignId('asset_id')
                ->constrained('assets')
                ->onDelete('cascade');
            
            // Interface identification
            $table->string('name'); // ge-0/0/0, eth0, etc.
            $table->string('display_name')->nullable(); // Friendly name
            $table->text('description')->nullable();
            
            // Interface metadata
            $table->string('type')->nullable(); // ethernet, loopback, vlan, etc.
            $table->string('mac_address', 17)->nullable(); // MAC address format
            $table->bigInteger('speed')->nullable(); // bits per second
            
            // Status
            $table->string('status')->nullable(); // up, down, testing, etc.
            $table->boolean('is_management')->default(false);
            
            // Source identity (following existing pattern)
            $table->string('provider')->nullable(); // librenms, uisp, manual
            $table->string('external_type')->nullable(); // port, interface, etc.
            $table->string('external_id')->nullable(); // Provider-specific ID
            
            // Metadata
            $table->jsonb('metadata')->nullable();
            
            // Timestamps
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('asset_id');
            $table->index('name');
            $table->index('mac_address');
            $table->index('provider');
            $table->index('external_id');
            
            // Unique: Only one interface per asset with the same name
            $table->unique(['asset_id', 'name']);
            
            // Unique: Provider external identity (matching existing pattern)
            $table->unique(['provider', 'external_type', 'external_id'], 'asset_interfaces_provider_unique')
                ->whereNotNull('provider')
                ->whereNotNull('external_type')
                ->whereNotNull('external_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_interfaces');
    }
};
