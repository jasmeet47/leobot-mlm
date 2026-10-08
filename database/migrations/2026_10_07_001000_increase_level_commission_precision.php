<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        /*
         * SQLite is used for automated tests.
         *
         * SQLite does not support PostgreSQL's
         * ALTER COLUMN ... TYPE syntax.
         *
         * Skip this precision adjustment in SQLite.
         */
        if ($driver === 'sqlite') {
            return;
        }

        /*
         * Preserve the original PostgreSQL behavior.
         */
        if ($driver === 'pgsql') {
            DB::statement("
                ALTER TABLE level_settings
                ALTER COLUMN commission_percentage TYPE NUMERIC(12, 9)
            ");

            return;
        }

        throw new \RuntimeException(
            'Unsupported database driver: ' . $driver
        );
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        /*
         * Nothing was changed for SQLite.
         */
        if ($driver === 'sqlite') {
            return;
        }

        /*
         * Original PostgreSQL rollback behavior.
         */
        if ($driver === 'pgsql') {
            DB::statement("
                ALTER TABLE level_settings
                ALTER COLUMN commission_percentage TYPE NUMERIC(8, 4)
            ");

            return;
        }

        throw new \RuntimeException(
            'Unsupported database driver: ' . $driver
        );
    }
};
