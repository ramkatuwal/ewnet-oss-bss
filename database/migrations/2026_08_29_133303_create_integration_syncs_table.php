<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_syncs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_id')->constrained('integrations')->cascadeOnDelete();
            $table->string('operation'); // full, incremental, manual, scheduled, webhook
            $table->string('status')->default('pending'); // pending, running, completed, failed, cancelled
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('records_processed')->default(0);
            $table->unsignedInteger('records_created')->default(0);
            $table->unsignedInteger('records_updated')->default(0);
            $table->unsignedInteger('records_unchanged')->default(0);
            $table->unsignedInteger('records_failed')->default(0);
            $table->text('error_summary')->nullable(); // sanitized error info, no secrets
            $table->json('metadata')->nullable(); // additional sync context
            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('integration_id');
            $table->index('status');
            $table->index('operation');
            $table->index('started_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_syncs');
    }
};
