<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beta_registration_invites', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code_hash', 64)->unique();
            $table->timestamp('used_at')->nullable();
            $table->foreignId('used_by_user_id')->nullable()->index();
        });
        foreach ([
            'aba9b6337bb3ca4c97f7dda8173214c994c52e6e974da5ac2bc638e523bc1cf4',
            'bdb2bcf3a15cc28c8c302dd0c162b71e93f8b94bc0ae568e85784a00f1f31dc4',
            '4f86ac677ba22960936be3b5c81056a9148e6432b6f45abeff0a76684cb42051',
            '771eea13d520d9d1de9eb6c4707886fd20a159138efb5664ba48faaf17b66767',
            'd8709235122ed3a38f7056b2cd6e77c4dd6d215a3767a77ff408884fe5a51ceb',
            '14ecb4374a985b762b0772dff2ce88aac2617fd168878937ed43303069cb65b8',
            '58676dc1c7376353e26de8b25337d173d2e489ed1a66b3ddf32e765a1d0cbd69',
            '2e5da0054766f1870606f87dc65908c9de62459ae5b7f1e5f0cc90eb7005c49e',
            'b086a530171940797cca08e16a5c88d09c9464f3a809cbe8a49ff66b9e23e82b',
            '145329b75824eeeceea2ec60f2c9bd3fb688f624c203ae19d1f2a96dd3c0b17a',
            '44b28ebb99b109f1ef66174b4e2722d67cfdc81701c8921c38004dc4f2333760',
            'e39823e3e18f6dd3bfae397767c148b1dd361d4103162469ca2074b8a7015834',
            'd9edcda0d40dd244d3a8c8ff7902e8de68cceffb63331d217470207b20e05eea',
            'b71e167a4863bc6e25b7c82db87d558cf28cf777d4b9d78997959ffa80b27a04',
            'fe45dd4f10d4026557d5949e3c1e78d607d54647cc06fbbbe078805fad4ae567',
            'c11f787243caace5eb3a2ae17b56da739e41a9d7d4e01f531e86291a11ce43f9',
            'd2d8930315e4ada27f7eac0e8ffa5754f8ac58d6fccc54523690d48c95a02d22',
            '73edae55d975a8baaea244e8b82d14aa0059f86c75847e587b83acfadfc8cfff',
            '44661f0ea3229ae7ca8b062de9b7e02aefb4e40b851a1347e93c86fbbe41bfda',
            'e768469af1b964f14ac1fe4b965b9533601d8b263c176cf0c11fc6a4aaf25a0c',
        ] as $hash) {
            DB::table('beta_registration_invites')->insert(['code_hash' => $hash]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('beta_registration_invites');
    }
};