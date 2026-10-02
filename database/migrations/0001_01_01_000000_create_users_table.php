<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username')->unique(); // यूनीक आईडी जैसे TX123456
            $table->string('sponsor_id')->nullable(); // अपलाइन का यूजरनेम
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone');
            $table->string('password');
            $table->string('security_pin'); // P2P और विड्रॉल के लिए 6 डिजिट पिन
            $table->enum('status', ['active', 'inactive'])->default('inactive');
            $table->rememberToken();
            $table->timestamps();

            // 41 लेवल्स की स्पीड बढ़ाने के लिए डेटाबेस इंडेक्सिंग
            $table->index('username');
            $table->index('sponsor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
