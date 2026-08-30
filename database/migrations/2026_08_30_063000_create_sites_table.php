<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->string('site_code')->unique();
            $table->string('name');
            $table->string('type'); // pop, tower, office, warehouse, datacenter, customer_premises, solar_site, repeater_site, other
            $table->string('status')->default('planned'); // planned, active, maintenance, inactive, decommissioned
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            
            // Physical Location (Optional)
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('altitude', 8, 2)->nullable();
            
            // Address (Optional)
            $table->string('province')->nullable();
            $table->string('district')->nullable();
            $table->string('municipality')->nullable();
            $table->string('ward')->nullable();
            $table->string('tole')->nullable();
            $table->text('address')->nullable();
            $table->string('postal_code')->nullable();
            
            // Organizational Ownership (Optional)
            $table->foreignId('company_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('region_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('branch_id')->nullable()->constrained()->onDelete('set null');
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['company_id', 'region_id', 'branch_id']);
            $table->index(['latitude', 'longitude']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sites');
    }
};
