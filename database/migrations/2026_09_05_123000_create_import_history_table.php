<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('import_history', function (Blueprint $table) {
            $table->id();
            
            // Source and Type identification
            $table->string('source', 50)->nullable()->index(); // e.g., 'uisp', 'librenms'
            $table->string('type', 50)->nullable()->index();   // e.g., 'device', 'site'
            
            // Integration context
            $table->foreignId('integration_id')->nullable()->constrained('integrations')->onDelete('set null');
            
            // Status tracking
            $table->string('status', 50)->default('pending')->index(); // pending, running, completed, failed
            
            // Actor tracking
            $table->foreignId('started_by')->nullable()->constrained('users')->onDelete('set null');
            
            // Timestamps
            $table->timestamp('started_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable()->index();
            $table->timestamps();
            
            // Statistics
            $table->integer('total_records')->default(0);
            $table->integer('created_records')->default(0);
            $table->integer('updated_records')->default(0);
            $table->integer('skipped_records')->default(0);
            $table->integer('conflict_records')->default(0);
            $table->integer('error_records')->default(0);
            
            // Error handling
            $table->text('error_message')->nullable();
            
            // Flexible metadata for summary info (not per-record details)
            $table->jsonb('metadata')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_history');
    }
};
