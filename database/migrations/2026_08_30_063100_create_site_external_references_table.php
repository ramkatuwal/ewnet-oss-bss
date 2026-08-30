<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_external_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->onDelete('restrict');
            $table->string('provider'); // librenms, uisp, etc.
            $table->string('external_type')->nullable(); // device, site, etc.
            $table->string('external_id');
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->unique(['provider', 'external_type', 'external_id']);
            $table->index(['provider', 'external_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_external_references');
    }
};
