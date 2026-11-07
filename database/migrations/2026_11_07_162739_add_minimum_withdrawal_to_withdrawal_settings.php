<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('withdrawal_settings', function (Blueprint $t): void {
            $t->decimal('minimum_withdrawal_usdt', 20, 8)->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('withdrawal_settings', function (Blueprint $t): void {
            $t->dropColumn('minimum_withdrawal_usdt');
        });
    }
};
