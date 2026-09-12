<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SEQUENCE IF NOT EXISTS asset_tag_seq START WITH 1 INCREMENT BY 1');

        // Seed the sequence beyond any existing numeric AST-<n> tags so manually
        // imported tags and auto-generated tags never collide.
        $maxSeq = (int) DB::table('assets')
            ->where('asset_tag', '~', '^AST-[0-9]+$')
            ->selectRaw("COALESCE(MAX(SUBSTRING(asset_tag FROM '^AST-([0-9]+)$')::bigint), 0) AS max_seq")
            ->value('max_seq');

        if ($maxSeq >= 1) {
            DB::statement('SELECT setval(\'asset_tag_seq\', ?, true)', [$maxSeq]);
        } else {
            // No numeric AST-<n> tags exist yet: keep the sequence "unused" so
            // the first nextval() returns 1.
            DB::statement('SELECT setval(\'asset_tag_seq\', 1, false)');
        }
    }

    public function down(): void
    {
        DB::statement('DROP SEQUENCE IF EXISTS asset_tag_seq');
    }
};
