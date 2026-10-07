<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('activation_balance', 20, 8)
                ->default(0)
                ->after('security_pin');

            $table->decimal('available_balance', 20, 8)
                ->default(0)
                ->after('activation_balance');

            $table->decimal('total_investment', 20, 8)
                ->default(0)
                ->after('available_balance');

            $table->decimal('lifetime_income', 20, 8)
                ->default(0)
                ->after('total_investment');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'activation_balance',
                'available_balance',
                'total_investment',
                'lifetime_income',
            ]);
        });
    }
};