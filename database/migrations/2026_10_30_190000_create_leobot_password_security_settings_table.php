<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('leobot_password_security_settings')) {
            Schema::create('leobot_password_security_settings', function (Blueprint $table) {
                $table->unsignedBigInteger('id')->primary();
                $table->boolean('enabled')->default(false);
                $table->timestamps();
            });
        }

        DB::table('leobot_password_security_settings')
            ->insertOrIgnore([
                'id' => 1,
                'enabled' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Preserve security settings and avoid destructive rollback.
    }
};
