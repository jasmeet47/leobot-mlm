<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activation_packages', function (Blueprint $table) {
            $table->id();

            /*
             * Member who owns this activation package.
             */
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            /*
             * Unique activation reference.
             * Used to prevent duplicate activation records.
             */
            $table->string('activation_key', 100)
                ->unique();

            /*
             * Original investment amount.
             * Each activation has its own investment.
             */
            $table->decimal(
                'investment_amount',
                20,
                8
            );

            /*
             * PACKAGE-WISE ROI CONTROL
             *
             * Default OFF until Admin enables it.
             */
            $table->boolean('roi_enabled')
                ->default(false);

            /*
             * ROI percentage per distribution.
             *
             * Example:
             * 0.200000000 = 0.20%
             */
            $table->decimal(
                'roi_percentage',
                12,
                9
            )->default(0);

            /*
             * ROI Distribution Mode:
             *
             * daily
             * weekly
             * monthly
             * manual
             *
             * Global OFF will be checked separately
             * before any distribution.
             */
            $table->string(
                'roi_distribution_mode',
                20
            )->default('manual');

            /*
             * PACKAGE-WISE ROI CAPPING
             *
             * Admin configurable:
             * 2x, 3x, 4x, 5x or custom.
             */
            $table->decimal(
                'roi_cap_multiplier',
                10,
                4
            )->default(3);

            /*
             * PACKAGE-WISE MAGIC INCOME CAPPING
             *
             * This is independent from ROI capping.
             *
             * Generation Income is not capped
             * by this multiplier.
             */
            $table->decimal(
                'magic_cap_multiplier',
                10,
                4
            )->default(3);

            /*
             * ROI income already paid for this package.
             */
            $table->decimal(
                'roi_income_paid',
                20,
                8
            )->default(0);

            /*
             * Magic income already paid for this package.
             */
            $table->decimal(
                'magic_income_paid',
                20,
                8
            )->default(0);

            /*
             * Distribution tracking.
             *
             * Duplicate payouts will additionally be
             * prevented using unique payout records
             * and transaction keys.
             */
            $table->timestamp('roi_last_distributed_at')
                ->nullable();

            $table->timestamp('roi_next_due_at')
                ->nullable();

            /*
             * Package status:
             *
             * pending
             * active
             * closed
             */
            $table->string('status', 20)
                ->default('pending');

            $table->timestamp('activated_at')
                ->nullable();

            $table->timestamps();

            $table->index([
                'user_id',
                'status',
            ]);

            $table->index([
                'roi_enabled',
                'roi_distribution_mode',
                'roi_next_due_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activation_packages');
    }
};
