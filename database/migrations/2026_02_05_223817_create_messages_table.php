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
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->morphs('messageable');
            $table->nullableMorphs('senderable');
            $table->string('type')->nullable();
            $table->string('tag')->nullable();
            $table->text('text')->nullable();
            $table->json('attachments')->nullable();
            $table->json('actions')->nullable();
            $table->json('errors')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['messageable_type', 'messageable_id', 'created_at'], 'messages_messageable_created_at_index');
            $table->index(['senderable_type', 'senderable_id', 'created_at'], 'messages_sender_created_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
