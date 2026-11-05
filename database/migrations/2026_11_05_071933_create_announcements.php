<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $t): void {
            $t->id();
            $t->string('title', 160);
            $t->string('heading', 200)->nullable();
            $t->string('subheading', 250)->nullable();
            $t->text('content')->nullable();
            $t->string('type', 30);
            $t->string('position', 30);
            $t->string('audience', 30);
            $t->json('pages');
            $t->string('member_group', 20)->nullable();
            $t->string('button_text', 80)->nullable();
            $t->string('button_url', 2048)->nullable();
            $t->string('image_alt', 200)->nullable();
            $t->text('image_base64')->nullable();
            $t->string('image_mime', 30)->nullable();
            $t->string('status', 20)->default('draft');
            $t->string('priority', 20)->default('normal');
            $t->string('frequency', 30)->default('session');
            $t->unsignedSmallInteger('delay_seconds')->default(0);
            $t->timestampTz('starts_at')->nullable();
            $t->timestampTz('ends_at')->nullable();
            $t->unsignedInteger('revision')->default(1);
            $t->unsignedBigInteger('updated_by');
            $t->timestampsTz();
            $t->index(['status', 'starts_at', 'ends_at']);
        });
        Schema::create('announcement_settings', function (Blueprint $t): void {
            $t->id();
            $t->unsignedSmallInteger('upload_limit_kb')->default(2048);
            $t->timestampsTz();
        });
        Schema::create('announcement_audits', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('announcement_id')->nullable();
            $t->unsignedBigInteger('actor_id');
            $t->string('action', 30);
            $t->json('before_json')->nullable();
            $t->json('after_json')->nullable();
            $t->timestampTz('created_at');
        });
        Schema::create('announcement_events', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('revision');
            $t->string('viewer_hash', 64);
            $t->string('window_key', 80);
            $t->timestampTz('viewed_at');
            $t->timestampTz('clicked_at')->nullable();
            $t->unique(['announcement_id', 'revision', 'viewer_hash', 'window_key'], 'announcement_event_once');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_events');
        Schema::dropIfExists('announcement_audits');
        Schema::dropIfExists('announcement_settings');
        Schema::dropIfExists('announcements');
    }
};
