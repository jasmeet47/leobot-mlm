<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('deleted_at')->nullable()->index();
        });
        Schema::create('member_retirement_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('member_user_id');
            $table->unsignedBigInteger('admin_user_id');
            $table->string('member_username');
            $table->string('action', 30);
            $table->text('reason');
            $table->timestamps();
            $table->index(['member_user_id', 'created_at']);
        });
        Schema::create('reserved_member_identities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('original_user_id')->unique();
            $table->string('username')->unique();
            $table->timestamps();
        });
    }
    public function down(): void {
        // Intentional: identity and audit history must not be destroyed by rollback.
        throw new RuntimeException('Irreversible retention migration; manual reviewed rollback required.');
    }
};
