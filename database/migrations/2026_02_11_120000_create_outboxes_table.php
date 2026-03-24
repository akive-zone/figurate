<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outboxes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('thread_id')->constrained('threads')->cascadeOnDelete();
            $table->foreignId('message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->string('direction', 20)->default('outbound');
            $table->string('protocol', 40);
            $table->string('provider', 120)->nullable();
            $table->string('target', 255)->nullable();
            $table->string('status', 32)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('available_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('idempotency_key', 190)->nullable();
            $table->json('payload')->nullable();
            $table->json('result')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['direction', 'status', 'available_at'], 'outboxes_direction_status_available_index');
            $table->index(['thread_id', 'protocol', 'provider'], 'outboxes_thread_protocol_provider_index');
            $table->unique(['idempotency_key'], 'outboxes_idempotency_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outboxes');
    }
};
