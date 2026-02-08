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
            $table->morphs('messageable');
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type')->default('text');
            $table->string('tag')->nullable();
            $table->text('body')->nullable();
            $table->json('attachments')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['messageable_type', 'messageable_id', 'created_at'], 'messages_messageable_created_at_index');
            $table->index(['sender_id', 'created_at'], 'messages_sender_created_at_index');
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
