<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Preserve user.status (active/inactive) and existing team counters.
            $table->boolean('is_blocked')->default(false);
            $table->timestamp('account_blocked_at')->nullable();
            // Zero is compatible with sessions created before this feature.
            $table->unsignedInteger('member_login_version')->default(0);
        });

        Schema::create('member_account_block_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('member_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('admin_user_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 10); // blocked | unblocked
            $table->string('previous_status', 20);
            $table->string('new_status', 20);
            $table->string('reason', 500);
            $table->timestamps();
            $table->index(['member_user_id', 'id']);
        });
    }

    public function down(): void
    {
        // Reverting would discard the only independent record of block reasons
        // and could revive revoked sessions. Intentionally fail closed.
        throw new RuntimeException('Member block audit history must not be dropped.');
    }
};
