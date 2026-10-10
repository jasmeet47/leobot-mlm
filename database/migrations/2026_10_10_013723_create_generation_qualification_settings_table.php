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
         * LeoBot 51-Level Business Qualification.
         *
         * Shared by:
         * 1. Trading Profit Generation Income.
         * 2. Investment Distribution Income.
         *
         * Business amounts are cumulative within
         * each specified level range.
         *
         * Level 1 has no business requirement.
         *
         * This migration creates settings only.
         * It does not calculate or pay commission.
         */

        Schema::create(
            'generation_qualification_settings',
            function (Blueprint $table) {
                $table->id();

                // Highest level unlocked by this rule.
                $table->unsignedTinyInteger(
                    'unlock_through_level'
                )->unique();

                // Minimum business required in USD.
                $table->decimal(
                    'required_business',
                    20,
                    8
                )->default(0);

                // Team levels used to calculate business.
                $table->unsignedTinyInteger(
                    'business_from_level'
                );

                $table->unsignedTinyInteger(
                    'business_to_level'
                );

                $table->timestamps();
            }
        );

        /*
         * Default Qualification Rules:
         *
         * No condition -> Level 1
         * 500 USD from Level 1 -> Level 2
         * 1500 USD from Levels 1-2 -> Level 4
         * 3000 USD from Levels 1-4 -> Level 6
         * 6000 USD from Levels 1-6 -> Level 8
         * 11000 USD from Levels 1-10 -> Level 51
         *
         * Team business counts independently of
         * whether a commission level is unlocked.
         */

        $rules = [
            [
                'unlock_through_level' => 1,
                'required_business' => '0.00000000',
                'business_from_level' => 1,
                'business_to_level' => 1,
            ],
            [
                'unlock_through_level' => 2,
                'required_business' => '500.00000000',
                'business_from_level' => 1,
                'business_to_level' => 1,
            ],
            [
                'unlock_through_level' => 4,
                'required_business' => '1500.00000000',
                'business_from_level' => 1,
                'business_to_level' => 2,
            ],
            [
                'unlock_through_level' => 6,
                'required_business' => '3000.00000000',
                'business_from_level' => 1,
                'business_to_level' => 4,
            ],
            [
                'unlock_through_level' => 8,
                'required_business' => '6000.00000000',
                'business_from_level' => 1,
                'business_to_level' => 6,
            ],
            [
                'unlock_through_level' => 51,
                'required_business' => '11000.00000000',
                'business_from_level' => 1,
                'business_to_level' => 10,
            ],
        ];

        foreach ($rules as $rule) {
            DB::table(
                'generation_qualification_settings'
            )->insert([
                'unlock_through_level' =>
                    $rule['unlock_through_level'],

                'required_business' =>
                    $rule['required_business'],

                'business_from_level' =>
                    $rule['business_from_level'],

                'business_to_level' =>
                    $rule['business_to_level'],

                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'generation_qualification_settings'
        );
    }
};
