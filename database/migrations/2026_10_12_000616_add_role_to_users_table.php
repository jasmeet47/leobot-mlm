<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add role-based access control to users.
     *
     * Security rules:
     * - All existing users receive the member role.
     * - New registrations default to member.
     * - No account is automatically promoted to admin.
     * - Passwords, PINs, balances and sponsor data are untouched.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)
                ->default('member');
        });
    }

    /**
     * Rollback removes only the role column.
     *
     * WARNING:
     * Rolling back after assigning admin roles removes
     * those assignments. Do not roll back on production
     * without a reviewed recovery plan.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};