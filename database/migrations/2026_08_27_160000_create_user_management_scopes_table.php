<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_management_scopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('scope_type', 20); // company, region, branch, department
            $table->unsignedBigInteger('scope_id');
            $table->foreignId('granted_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('granted_at')->useCurrent();
            $table->timestamps();

            // Prevent duplicate scope assignments
            $table->unique(['user_id', 'scope_type', 'scope_id']);

            // Indexes for efficient scope lookups
            $table->index('user_id');
            $table->index(['scope_type', 'scope_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_management_scopes');
    }
};
