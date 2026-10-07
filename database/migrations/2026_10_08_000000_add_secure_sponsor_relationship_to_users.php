<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Add a permanent database-level parent relationship.
         *
         * sponsor_user_id stores the actual users.id of the sponsor.
         * The old sponsor_id column is kept for compatibility.
         */

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('sponsor_user_id')
                ->nullable()
                ->after('sponsor_id');

            $table->index('sponsor_user_id');
        });

        /*
         * Convert existing sponsor usernames into real user IDs.
         *
         * Example:
         * ADMIN -> id 1
         * TX736748 -> sponsor_user_id = 1
         */

        $users = DB::table('users')
            ->select('id', 'sponsor_id')
            ->whereNotNull('sponsor_id')
            ->get();

        foreach ($users as $user) {
            $sponsor = DB::table('users')
                ->select('id')
                ->where('username', $user->sponsor_id)
                ->first();

            if (!$sponsor) {
                throw new RuntimeException(
                    "Invalid sponsor '{$user->sponsor_id}' for user ID {$user->id}."
                );
            }

            DB::table('users')
                ->where('id', $user->id)
                ->update([
                    'sponsor_user_id' => $sponsor->id,
                    'updated_at' => now(),
                ]);
        }

        /*
         * Protect the relationship at database level.
         *
         * A sponsor cannot be deleted while members are attached to them.
         * This prevents orphaned referral-tree records.
         */

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('sponsor_user_id')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['sponsor_user_id']);
            $table->dropIndex(['sponsor_user_id']);
            $table->dropColumn('sponsor_user_id');
        });
    }
};