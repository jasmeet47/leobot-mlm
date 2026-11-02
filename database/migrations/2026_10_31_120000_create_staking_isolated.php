<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('staking_controls', function(Blueprint $t){$t->id();$t->boolean('enabled')->default(false);$t->timestamps();});
  \Illuminate\Support\Facades\DB::table('staking_controls')->insert(['enabled'=>false,'created_at'=>now(),'updated_at'=>now()]);
  Schema::create('staking_positions', function(Blueprint $t){$t->id();$t->foreignId('user_id')->constrained('users');$t->decimal('amount',20,8);$t->string('request_key',100)->unique();$t->string('status')->default('active');$t->timestamps();});
  Schema::create('staking_control_audits', function(Blueprint $t){$t->id();$t->foreignId('admin_id')->constrained('users');$t->boolean('old_enabled');$t->boolean('new_enabled');$t->timestamps();});
 }
 public function down(): void {Schema::dropIfExists('staking_control_audits');Schema::dropIfExists('staking_positions');Schema::dropIfExists('staking_controls');}
};
