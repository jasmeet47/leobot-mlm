<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('mt5_display_settings', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->boolean('enabled')->default(false);
            $table->timestamp('updated_at')->nullable();
        });
        DB::table('mt5_display_settings')->insert(['id' => 1, 'enabled' => false, 'updated_at' => now()]);
    }
    public function down(): void
    {
        Schema::dropIfExists('mt5_display_settings');
    }
};