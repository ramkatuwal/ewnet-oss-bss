<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->onDelete('restrict');
            $table->string('path');
            $table->string('title')->nullable();
            $table->string('category')->default('site'); // site, rack, power, tower, equipment, other
            $table->text('description')->nullable();
            $table->timestamp('taken_at')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_photos');
    }
};
