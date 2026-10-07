<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_ledger', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('entry_type');

            $table->string('wallet_type')->nullable();

            $table->decimal('amount', 20, 8);

            $table->decimal('balance_before', 20, 8)->default(0);

            $table->decimal('balance_after', 20, 8)->default(0);

            $table->foreignId('from_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('reference_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->unsignedSmallInteger('level')->nullable();

            $table->string('reference_type')->nullable();

            $table->unsignedBigInteger('reference_id')->nullable();

            $table->string('status')->default('completed');

            $table->text('description')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'entry_type']);
            $table->index(['reference_type', 'reference_id']);
            $table->index(['from_user_id', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_ledger');
    }
};