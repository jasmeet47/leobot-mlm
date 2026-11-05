<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staking_profit_statements', function (Blueprint $table) {
            $table->string('distribution_version', 32)->default('legacy_direct_100');
            $table->foreignId('profit_sharing_funding_id')->nullable()->unique()->constrained('profit_sharing_fundings')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Financial distribution history must be preserved; use a reviewed forward migration.');
    }
};
