<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staking_profit_statements', fn (Blueprint $table) => $table->string('verification_method', 40)->default('independent_admin'));
        Schema::create('trading_profit_reviewers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->restrictOnDelete();
            $table->foreignId('granted_by')->constrained('users')->restrictOnDelete();
            $table->boolean('enabled');
            $table->timestamps();
        });
        Schema::create('trading_profit_reviewer_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('admin_id')->constrained('users')->restrictOnDelete();
            $table->boolean('enabled');
            $table->timestamps();
        });
        Schema::create('staking_profit_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('statement_id')->constrained('staking_profit_statements')->restrictOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
            $table->char('evidence_digest', 64);
            $table->timestamps();
            $table->index(['statement_id', 'reviewer_id']);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Reviewer and financial approval history requires a reviewed forward migration.');
    }
};
