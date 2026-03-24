<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_users', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('account_id');
            $table->foreignId('user_id');
            $table->string('type')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamp('linked_at')->nullable();
            $table->timestamp('unlinked_at')->nullable();
            $table->timestamps();

            $table->unique(['account_id', 'user_id'], 'account_users_unique');
            $table->index(['user_id', 'type', 'unlinked_at'], 'account_users_user_lookup_idx');
            $table->index(['account_id', 'type', 'unlinked_at'], 'account_users_account_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_users');
    }
};
