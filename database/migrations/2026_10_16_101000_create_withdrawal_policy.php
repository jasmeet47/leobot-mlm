<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('withdrawal_settings',function(Blueprint $t){
            $t->id();
            $t->decimal('principal_withdrawal_fee_percent',12,9)->default(15);
            $t->boolean('member_requests_enabled')->default(false);
            $t->boolean('admin_review_enabled')->default(false);
            $t->boolean('principal_release_enabled')->default(false); // requires independently audited principal balance
            $t->timestamps();
        });
        DB::table('withdrawal_settings')->insert([
            'id'=>1,'principal_withdrawal_fee_percent'=>'15.000000000',
            'member_requests_enabled'=>false,'admin_review_enabled'=>false,'principal_release_enabled'=>false,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        Schema::create('withdrawal_settings_audits',function(Blueprint $t){
            $t->id();
            $t->foreignId('admin_user_id')->constrained('users')->restrictOnDelete();
            $t->string('action',40);
            $t->string('old_value',40);
            $t->string('new_value',40);
            $t->timestamp('created_at');
        });
        Schema::table('withdrawal_requests',function(Blueprint $t){
            $t->timestamp('approved_at')->nullable();
            $t->timestamp('externally_paid_at')->nullable();
        });
    }
    public function down(): void {
        if (DB::table('withdrawal_settings_audits')->exists() ||
            DB::table('withdrawal_requests')->exists()) {
            throw new RuntimeException('Withdrawal history exists: rollback prohibited.');
        }
        Schema::table('withdrawal_requests',fn(Blueprint $t)=>$t->dropColumn(['approved_at','externally_paid_at']));
        Schema::dropIfExists('withdrawal_settings_audits');
        Schema::dropIfExists('withdrawal_settings');
    }
};
