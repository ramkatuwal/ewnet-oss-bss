<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Security & Authentication Fields
            if (!Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable();
            }
            if (!Schema::hasColumn('users', 'failed_login_attempts')) {
                $table->integer('failed_login_attempts')->default(0);
            }
            if (!Schema::hasColumn('users', 'locked_at')) {
                $table->timestamp('locked_at')->nullable();
            }
            if (!Schema::hasColumn('users', 'password_changed_at')) {
                $table->timestamp('password_changed_at')->nullable();
            }
            
            // Profile Fields
            if (!Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }
            if (!Schema::hasColumn('users', 'avatar')) {
                $table->string('avatar')->nullable();
            }
            if (!Schema::hasColumn('users', 'phone_number')) {
                $table->string('phone_number')->nullable();
            }
            if (!Schema::hasColumn('users', 'preferences')) {
                $table->json('preferences')->nullable();
            }
            if (!Schema::hasColumn('users', 'last_activity_at')) {
                $table->timestamp('last_activity_at')->nullable();
            }
            
            // Soft Delete (Critical - Missing!)
            if (!Schema::hasColumn('users', 'deleted_at')) {
                $table->softDeletes();
            }
            
            // Only add indexes that don't exist
            // Note: email index already exists, skip it
            // Only add phone_number index if it doesn't exist
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumnIfExists('last_login_at');
            $table->dropColumnIfExists('failed_login_attempts');
            $table->dropColumnIfExists('locked_at');
            $table->dropColumnIfExists('password_changed_at');
            $table->dropColumnIfExists('is_active');
            $table->dropColumnIfExists('avatar');
            $table->dropColumnIfExists('phone_number');
            $table->dropColumnIfExists('preferences');
            $table->dropColumnIfExists('last_activity_at');
            $table->dropColumnIfExists('deleted_at');
        });
    }
};
