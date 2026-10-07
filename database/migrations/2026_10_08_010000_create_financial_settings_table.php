<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_settings', function (Blueprint $table) {
            $table->id();
            $table->string('setting_key')->unique();
            $table->decimal('setting_value', 20, 8)->default(0);
            $table->string('description')->nullable();
            $table->timestamps();
        });

        DB::table('financial_settings')->insert([
            'setting_key' => 'principal_withdrawal_fee_percent',
            'setting_value' => 15.00000000,
            'description' => 'Principal withdrawal charge percentage. Admin configurable.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_settings');
    }
};