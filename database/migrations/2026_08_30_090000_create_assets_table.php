<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            
            // Organization (Derived via Site)
            $table->foreignId('site_id')->constrained()->onDelete('cascade');
            
            // Identification
            $table->string('asset_tag')->unique();
            $table->string('serial_number')->nullable();
            
            // Classification
            $table->string('category'); // POWER, NETWORK, INFRASTRUCTURE, OTHER
            $table->string('type'); // Battery, Router, Solar Panel, etc.
            $table->string('manufacturer')->nullable();
            $table->string('model')->nullable();
            
            // Inventory
            $table->integer('quantity')->default(1);
            $table->string('unit')->default('pcs');
            
            // Lifecycle
            $table->string('status'); // OPERATIONAL, SPARE, MAINTENANCE, FAULTY, RETIRED, MISSING, DISPOSED
            $table->string('condition')->nullable(); // EXCELLENT, GOOD, FAIR, POOR, CRITICAL
            
            // Dates
            $table->date('purchase_date')->nullable();
            $table->date('installation_date')->nullable();
            $table->date('warranty_expiry')->nullable();
            
            // Technical & Notes
            $table->jsonb('specifications')->nullable();
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            
            // Audit
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            
            $table->timestamps();
            $table->softDeletes();
        });

        // Indexes for performance and reporting
        Schema::table('assets', function (Blueprint $table) {
            $table->index(['category', 'type']);
            $table->index(['status', 'condition']);
            $table->index('purchase_date');
            
            // Partial unique index for serial numbers (only enforce uniqueness if not null)
            // Note: PostgreSQL specific syntax handled via raw statement if needed, 
            // but Laravel's unique() on nullable column usually works as expected in PG.
            $table->unique('serial_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
