<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_agents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('kind')->default('web');
            $table->text('user_agent')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('app_version')->nullable();
            $table->string('platform')->nullable();
            $table->jsonb('data')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'last_seen_at'], 'user_agents_user_last_seen_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_agents');
    }
};
