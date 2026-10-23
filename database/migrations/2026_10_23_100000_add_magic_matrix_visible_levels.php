<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('magic_matrix_settings', function (Blueprint $table): void {
            $table->unsignedTinyInteger('visible_levels')->default(20);
        });
    }
    public function down(): void {
        Schema::table('magic_matrix_settings', function (Blueprint $table): void {
            $table->dropColumn('visible_levels');
        });
    }
};
