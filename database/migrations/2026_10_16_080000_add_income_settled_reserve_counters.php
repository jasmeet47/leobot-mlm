<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('profit_sharing_reserve_accounts', function (Blueprint $t) {
            $t->decimal('total_self_settled',20,8)->default(0);
            $t->decimal('total_magic_settled',20,8)->default(0);
        });
        $a=DB::table('profit_sharing_reserve_accounts')->where('id',1)->first();
        if (!$a || bccomp((string)$a->self_balance,(string)$a->total_self_funded,8)!==0 ||
            bccomp((string)$a->magic_balance,(string)$a->total_magic_funded,8)!==0) {
            throw new RuntimeException('Existing financial history must be reconciled before adding settlement counters.');
        }
    }
    public function down(): void {
        $a=DB::table('profit_sharing_reserve_accounts')->where('id',1)->first();
        if ($a && (bccomp((string)$a->total_self_settled,'0',8)!==0 ||
                   bccomp((string)$a->total_magic_settled,'0',8)!==0)) {
            throw new RuntimeException('Cannot roll back completed settlements.');
        }
        Schema::table('profit_sharing_reserve_accounts',function(Blueprint $t){
            $t->dropColumn(['total_self_settled','total_magic_settled']);
        });
    }
};
