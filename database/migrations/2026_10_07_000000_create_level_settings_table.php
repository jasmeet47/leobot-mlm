<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('level_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('level_number')->unique();
            $table->decimal('commission_percentage', 8, 4)->default(0);
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        for ($level = 1; $level <= 51; $level++) {
            DB::table('level_settings')->insert([
                'level_number' => $level,
                'commission_percentage' => 0,
                'is_active' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('level_settings');
    }
};