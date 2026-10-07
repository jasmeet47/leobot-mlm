<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_ledger', function (Blueprint $table) {
            $table->string('transaction_key')
                ->nullable()
                ->unique();
        });
    }

    public function down(): void
    {
        Schema::table('financial_ledger', function (Blueprint $table) {
            $table->dropUnique(['transaction_key']);
            $table->dropColumn('transaction_key');
        });
    }
};