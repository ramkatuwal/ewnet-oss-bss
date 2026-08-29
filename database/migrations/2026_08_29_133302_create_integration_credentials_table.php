<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_id')->constrained('integrations')->cascadeOnDelete();
            $table->string('credential_type'); // api_token, username_password, ssh_key, shared_secret, certificate, oauth, none
            $table->string('label')->nullable(); // human-readable label (e.g., "Primary API Key")
            $table->text('encrypted_value'); // encrypted sensitive data
            $table->string('masked_hint')->nullable(); // e.g., "************7F3A"
            $table->json('metadata')->nullable(); // non-sensitive metadata (username, key_id, cert_cn, etc.)
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index('integration_id');
            $table->index('credential_type');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_credentials');
    }
};
