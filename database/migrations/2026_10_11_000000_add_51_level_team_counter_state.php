<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Preserve existing integer precision (20 digits), adding 8 decimals.
        Schema::table('member_teams', function (Blueprint $table): void {
            $table->decimal('total_business', 28, 8)->default(0)->change();
        });

        // Applied snapshot prevents repeated registration/activation syncs
        // from double-counting even when a caller retries the same request.
        Schema::create('member_team_states', function (Blueprint $table): void {
            $table->foreignId('user_id')->primary()
                ->constrained('users')->cascadeOnDelete();
            $table->foreignId('sponsor_user_id')->nullable()
                ->constrained('users')->restrictOnDelete();
            $table->boolean('is_counted')->default(false);
            $table->boolean('is_active')->default(false);
            $table->decimal('active_business', 28, 8)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Rollback would round 8-decimal values to 4 places. Refuse it
        // rather than silently lose financial/business precision.
        throw new RuntimeException(
            'Unsafe rollback: member_teams.total_business is DECIMAL(28,8). '
            . 'Restore from a verified backup if this schema must be reversed.'
        );
    }
};
