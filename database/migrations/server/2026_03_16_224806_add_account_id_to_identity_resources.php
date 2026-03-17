<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channels', function (Blueprint $table): void {
            $table->foreignId('account_id')->nullable()->after('uuid')->constrained('accounts')->nullOnDelete();
            $table->index(['account_id', 'status'], 'channels_account_status_idx');
        });

        Schema::table('threads', function (Blueprint $table): void {
            $table->foreignId('account_id')->nullable()->after('uuid')->constrained('accounts')->nullOnDelete();
            $table->index(['account_id', 'status'], 'threads_account_status_idx');
        });

        Schema::table('agent_tasks', function (Blueprint $table): void {
            $table->foreignId('account_id')->nullable()->after('uuid')->constrained('accounts')->nullOnDelete();
            $table->index(['account_id', 'status'], 'agent_tasks_account_status_idx');
        });

        Schema::table('agent_conversations', function (Blueprint $table): void {
            $table->foreignId('account_id')->nullable()->after('id')->constrained('accounts')->nullOnDelete();
            $table->index(['account_id', 'updated_at'], 'agent_conversations_account_updated_idx');
        });

        Schema::table('thread_actor_sessions', function (Blueprint $table): void {
            $table->foreignId('account_id')->nullable()->after('thread_actor_id')->constrained('accounts')->nullOnDelete();
            $table->index(['account_id', 'thread_id'], 'thread_actor_sessions_account_thread_idx');
        });
    }

    public function down(): void
    {
        Schema::table('thread_actor_sessions', function (Blueprint $table): void {
            $table->dropIndex('thread_actor_sessions_account_thread_idx');
            $table->dropConstrainedForeignId('account_id');
        });

        Schema::table('agent_conversations', function (Blueprint $table): void {
            $table->dropIndex('agent_conversations_account_updated_idx');
            $table->dropConstrainedForeignId('account_id');
        });

        Schema::table('agent_tasks', function (Blueprint $table): void {
            $table->dropIndex('agent_tasks_account_status_idx');
            $table->dropConstrainedForeignId('account_id');
        });

        Schema::table('threads', function (Blueprint $table): void {
            $table->dropIndex('threads_account_status_idx');
            $table->dropConstrainedForeignId('account_id');
        });

        Schema::table('channels', function (Blueprint $table): void {
            $table->dropIndex('channels_account_status_idx');
            $table->dropConstrainedForeignId('account_id');
        });
    }
};
