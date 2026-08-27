<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Check if enum types exist, create if not
        DB::statement("DO \$\$ BEGIN
            IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'municipality_type_enum') THEN
                CREATE TYPE municipality_type_enum AS ENUM ('metropolitan', 'sub-metropolitan', 'municipality', 'rural-municipality');
            END IF;
        END \$\$");
    }

    public function down()
    {
        // Do not drop enums as they may be needed
    }
};
