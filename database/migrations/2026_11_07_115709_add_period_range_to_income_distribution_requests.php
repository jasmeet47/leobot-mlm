<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('magic_matrix_cycles', function (Blueprint $t): void {
            $t->foreignId('staking_position_id')->nullable()->unique()->constrained('staking_positions')->restrictOnDelete();
        });
        Schema::table('income_distribution_requests', function (Blueprint $t): void {
            $t->date('period_end_date')->nullable();
            $t->uuid('submission_token')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('magic_matrix_cycles', function (Blueprint $t): void {
            $t->dropConstrainedForeignId('staking_position_id');
        });
        Schema::table('income_distribution_requests', function (Blueprint $t): void {
            $t->dropColumn(['period_end_date', 'submission_token']);
        });
    }
};
