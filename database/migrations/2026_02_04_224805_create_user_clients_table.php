<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_clients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id');
            $table->text('user_agent')->nullable();
            $table->string('device_identifier')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('app_version')->nullable();
            $table->string('kind')->default('web');
            $table->string('platform')->nullable();
            $table->jsonb('data')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique('device_identifier', 'user_clients_device_identifier_unique');

            $table->index(['user_id', 'last_seen_at'], 'user_clients_user_last_seen_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_clients');
    }
};
