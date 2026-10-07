<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE level_settings
            ALTER COLUMN commission_percentage TYPE NUMERIC(12, 9)
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE level_settings
            ALTER COLUMN commission_percentage TYPE NUMERIC(8, 4)
        ");
    }
};