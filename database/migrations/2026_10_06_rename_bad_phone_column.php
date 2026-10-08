<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $oldColumn = "mobile No\nmobile No\nmobile No";

        /*
         * If users table does not exist,
         * stop rather than modifying anything.
         */
        if (!Schema::hasTable('users')) {
            throw new RuntimeException(
                'Users table does not exist.'
            );
        }

        /*
         * New installations already have
         * a correctly named phone column.
         *
         * No rename is needed.
         */
        if (Schema::hasColumn('users', 'phone')) {
            return;
        }

        /*
         * Rename the old malformed column
         * only if it actually exists.
         */
        if (Schema::hasColumn('users', $oldColumn)) {
            Schema::table('users', function (Blueprint $table) use ($oldColumn) {
                $table->renameColumn(
                    $oldColumn,
                    'phone'
                );
            });

            return;
        }

        /*
         * Neither column exists.
         * Fail safely instead of silently
         * continuing with a broken schema.
         */
        throw new RuntimeException(
            'Neither phone nor legacy mobile column exists.'
        );
    }

    public function down(): void
    {
        /*
         * Intentionally do not rename phone back.
         *
         * This legacy compatibility migration cannot
         * reliably tell whether phone was already
         * present or was renamed by this migration.
         *
         * Keeping the current column name avoids
         * damaging the normal users schema.
         */
    }
};
