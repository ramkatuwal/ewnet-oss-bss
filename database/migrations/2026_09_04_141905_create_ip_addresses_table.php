<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Create table without the ip_address column
        Schema::create('ip_addresses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('asset_interface_id')
                ->constrained('asset_interfaces')
                ->onDelete('cascade');

            $table->smallInteger('family')->nullable();
            $table->smallInteger('prefix_length')->nullable();

            $table->boolean('is_primary')->default(false);
            $table->boolean('is_management')->default(false);

            $table->string('provider')->nullable();
            $table->string('external_type')->nullable();
            $table->string('external_id')->nullable();

            $table->jsonb('metadata')->nullable();

            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            // Indexes without ip_address
            $table->index('asset_interface_id');
            $table->index('provider');
            $table->index('external_id');
            $table->index(['is_primary', 'is_management']);

            // Unique constraint for provider identity (no ip_address yet)
            $table->unique(['provider', 'external_type', 'external_id'], 'ip_addresses_provider_unique')
                ->whereNotNull('provider')
                ->whereNotNull('external_type')
                ->whereNotNull('external_id');
        });

        // Step 2: Add the ip_address column using raw SQL (PostgreSQL INET type)
        DB::statement('ALTER TABLE ip_addresses ADD COLUMN ip_address inet');

        // Step 3: Add the unique constraint for asset_interface_id + ip_address
        DB::statement('ALTER TABLE ip_addresses ADD CONSTRAINT ip_addresses_asset_interface_id_ip_address_unique UNIQUE (asset_interface_id, ip_address)');

        // Step 4: Add index on ip_address
        DB::statement('CREATE INDEX ip_addresses_ip_address_index ON ip_addresses (ip_address)');
    }

    public function down(): void
    {
        Schema::dropIfExists('ip_addresses');
    }
};
