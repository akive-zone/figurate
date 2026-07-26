<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $messagesTable = config('ai.conversations.tables.messages', 'agent_conversation_messages');

        Schema::table($messagesTable, function (Blueprint $table) {
            $table->string('invocation_id', 100)->nullable()->index();
            $table->string('trace_id', 100)->nullable()->index();
            $table->string('parent_invocation_id', 100)->nullable()->index();
            $table->foreignId('root_post_id')->nullable();
            $table->foreignId('output_post_id')->nullable();

            $table->index(['trace_id', 'parent_invocation_id'], 'agent_message_trace_parent_index');
            $table->index(['root_post_id', 'created_at'], 'agent_message_root_post_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $messagesTable = config('ai.conversations.tables.messages', 'agent_conversation_messages');

        Schema::table($messagesTable, function (Blueprint $table) {
            $table->dropIndex('agent_message_trace_parent_index');
            $table->dropIndex('agent_message_root_post_index');
            $table->dropConstrainedForeignId('root_post_id');
            $table->dropConstrainedForeignId('output_post_id');
            $table->dropColumn([
                'invocation_id',
                'trace_id',
                'parent_invocation_id',
            ]);
        });
    }
};
