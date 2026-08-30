<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_lifecycle_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->onDelete('cascade');
            $table->string('event_type', 50);
            $table->string('status_before', 50)->nullable();
            $table->string('status_after', 50)->nullable();
            $table->foreignId('from_site_id')->nullable()->constrained('sites')->onDelete('set null');
            $table->foreignId('to_site_id')->nullable()->constrained('sites')->onDelete('set null');
            $table->text('notes')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('event_date')->useCurrent();
            $table->timestamps();

            $table->index(['asset_id', 'event_date']);
            $table->index('event_type');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_lifecycle_events');
    }
};
