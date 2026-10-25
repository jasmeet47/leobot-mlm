<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('demo_sim_wallets',function(Blueprint $t){$t->id();$t->foreignId('user_id')->unique()->constrained('users')->restrictOnDelete();$t->decimal('balance',20,8)->default(0);$t->timestamps();});
  Schema::create('demo_sim_ledger',function(Blueprint $t){$t->id();$t->foreignId('user_id')->constrained('users')->restrictOnDelete();$t->string('reference',100)->unique();$t->string('kind',16);$t->decimal('amount',20,8);$t->decimal('balance_after',20,8);$t->timestamps();});
 }
 public function down(): void {Schema::dropIfExists('demo_sim_ledger');Schema::dropIfExists('demo_sim_wallets');}
};
