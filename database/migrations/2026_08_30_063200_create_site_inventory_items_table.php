<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->onDelete('restrict');
            $table->string('category'); // network_equipment, power, battery, solar, inverter, generator, rack, cabinet, ups, hvac, security, tools, spare, other
            $table->string('name');
            $table->string('asset_tag')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('model')->nullable();
            $table->integer('quantity')->default(1);
            $table->string('unit')->default('pcs');
            $table->string('status')->default('installed'); // installed, stored, spare, in_use, maintenance, damaged, retired, disposed
            $table->string('condition')->default('new'); // new, good, fair, poor, damaged
            $table->date('purchase_date')->nullable();
            $table->date('installation_date')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['site_id', 'category']);
            $table->index(['asset_tag', 'serial_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_inventory_items');
    }
};
