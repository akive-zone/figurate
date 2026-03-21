<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inboxes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('thread_id')->nullable()->constrained('threads')->nullOnDelete();
            $table->morphs('inboxable');
            $table->string('kind', 40)->default('thread');
            $table->string('status', 24)->default('unread');
            $table->string('title', 160);
            $table->text('summary')->nullable();
            $table->string('source', 80)->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'created_at'], 'inboxes_user_status_created_index');
            $table->index(['thread_id', 'created_at'], 'inboxes_thread_created_index');
            $table->unique(['user_id', 'inboxable_type', 'inboxable_id', 'kind'], 'inboxes_user_inboxable_kind_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inboxes');
    }
};
