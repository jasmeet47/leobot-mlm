<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_wallet', function (Blueprint $table) {
            $table->id();
            $table->decimal('balance', 20, 8)->default(0);
            $table->timestamps();
        });

        DB::table('company_wallet')->insert([
            'balance' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('company_wallet');
    }
};