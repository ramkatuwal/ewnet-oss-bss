<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE UNIQUE INDEX asset_refs_librenms_integration_unique ON asset_external_references (integration_id, provider, external_type, external_id) NULLS NOT DISTINCT WHERE provider = 'librenms' AND integration_id IS NOT NULL");
        DB::statement("CREATE UNIQUE INDEX asset_refs_legacy_provider_unique ON asset_external_references (provider, external_type, external_id) WHERE provider <> 'librenms' OR integration_id IS NULL");
        DB::statement('ALTER TABLE asset_external_references DROP CONSTRAINT asset_external_references_provider_external_type_external_id_unique');
    }

    public function down(): void
    {
        // Fail atomically rather than discard references if scoped IDs now overlap.
        DB::statement('ALTER TABLE asset_external_references ADD CONSTRAINT asset_external_references_provider_external_type_external_id_unique UNIQUE (provider, external_type, external_id)');
        DB::statement('DROP INDEX asset_refs_librenms_integration_unique');
        DB::statement('DROP INDEX asset_refs_legacy_provider_unique');
    }
};
