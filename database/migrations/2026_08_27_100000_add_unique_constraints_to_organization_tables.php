<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Regions: unique name within company
        Schema::table('regions', function (Blueprint $table) {
            $table->unique(['company_id', 'name'], 'regions_company_name_unique');
        });

        // Branches: unique name within region
        Schema::table('branches', function (Blueprint $table) {
            $table->unique(['region_id', 'name'], 'branches_region_name_unique');
        });

        // Departments: unique name within branch
        Schema::table('departments', function (Blueprint $table) {
            $table->unique(['branch_id', 'name'], 'departments_branch_name_unique');
        });
    }

    public function down(): void
    {
        Schema::table('regions', function (Blueprint $table) {
            $table->dropUnique('regions_company_name_unique');
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->dropUnique('branches_region_name_unique');
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->dropUnique('departments_branch_name_unique');
        });
    }
};
