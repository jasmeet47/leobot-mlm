<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('dashboard_quick_links', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 30)->unique();
            $table->string('url', 2048)->nullable();
            $table->boolean('enabled')->default(false);
            $table->timestamps();
        });

        foreach (['telegram', 'whatsapp', 'youtube', 'arattai', 'pdf'] as $platform) {
            DB::table('dashboard_quick_links')->insert([
                'platform' => $platform,
                'url' => null,
                'enabled' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_quick_links');
    }
};