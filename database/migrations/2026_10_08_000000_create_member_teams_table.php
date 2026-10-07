<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_teams', function (Blueprint $table) {
            $table->id();

            // जिस user की team है
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Upline level: 1 से 51
            $table->unsignedSmallInteger('level');

            // उस level पर inactive members
            $table->unsignedInteger('inactive_count')->default(0);

            // उस level पर active members
            $table->unsignedInteger('active_count')->default(0);

            // उस level से आने वाला total business
            $table->decimal('total_business', 20, 4)->default(0);

            $table->timestamps();

            // एक user के लिए एक level की केवल एक row
            $table->unique(['user_id', 'level']);

            // Fast dashboard/team queries
            $table->index(['user_id', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_teams');
    }
};