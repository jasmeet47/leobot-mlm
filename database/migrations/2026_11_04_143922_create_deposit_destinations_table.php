<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('deposit_destinations', function (Blueprint $table) {
            $table->id();
            $table->string('network', 10)->index();
            $table->string('address', 100);
            $table->text('qr_base64')->nullable();
            $table->string('qr_mime', 30)->nullable();
            $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('deposit_destinations')->exists()) {
            throw new RuntimeException('Receiving address history exists; destructive rollback prohibited.');
        }
        Schema::dropIfExists('deposit_destinations');
    }
};
